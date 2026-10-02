<?php

declare(strict_types=1);

namespace App\Services\TelegramOperator;

use App\Models\Task;
use App\Services\Orders\TelegramNotificationSelector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * One operational Telegram card per order destination (operator DMs).
 * NEW = compact SEND; later lifecycle states EDIT the stored message.
 * PAID always EDITs the authoritative card and SENDs exactly one short attention
 * alert (normal or frozen/high-severity). Risk/freeze never suppresses the alert.
 * Channel destinations are intentionally excluded from order-card ownership.
 */
final class TelegramOrderLifecyclePublisher
{
    public function __construct(
        private readonly TelegramOperatorBotClient $bot,
        private readonly TelegramOrderCardRegistry $registry,
        private readonly TelegramOrderMessagePresenter $presenter,
        private readonly TelegramNotificationSelector $selector,
        private readonly TelegramOperatorAuthService $auth,
    ) {
    }

    /**
     * @return array{sent: int, edited: int, recovered: int, skipped_stale: int, attention_alert?: int}
     */
    public function publishNew(Task $task): array
    {
        return $this->publish($task, TelegramOrderMessagePresenter::LIFECYCLE_NEW, allowSend: true, compactNew: true);
    }

    /**
     * EDIT the permanent card on PAID, then SEND exactly one short attention alert.
     * Frozen/high-risk changes alert severity only — never suppresses the paid event.
     *
     * @return array{sent: int, edited: int, recovered: int, skipped_stale: int, attention_alert: int, attention_alert_skip_reason?: string|null}
     */
    public function publishPaid(Task $task): array
    {
        $fresh = $task->fresh() ?? $task;
        $stats = $this->publish($fresh, TelegramOrderMessagePresenter::LIFECYCLE_PAID, allowSend: true, compactNew: false);
        $authLife = TelegramOrderMessagePresenter::lifecycleFromStatus((int) ($fresh->status ?? 0));
        if (TelegramOrderMessagePresenter::isTerminalLifecycle($authLife)
            || $authLife === TelegramOrderMessagePresenter::LIFECYCLE_COMPLETED
        ) {
            $stats['attention_alert'] = 0;

            return $stats;
        }

        $decision = $this->presenter->paidAttentionAlertDecision($fresh);
        if (! $decision['send']) {
            // Fail-closed policy hook: current decision always sends; keep path for safety.
            Log::info('telegram_order_card', [
                'order' => (string) ($fresh->public_id ?? $fresh->id ?? ''),
                'task_id' => (int) ($fresh->id ?? 0),
                'state' => TelegramOrderMessagePresenter::LIFECYCLE_PAID,
                'card_action' => 'EDIT',
                'attention_alert' => 'SKIP_POLICY',
                'attention_alert_result' => $decision['reason'],
                'dedup_key' => 'order:'.(int) ($fresh->id ?? 0).':PAID_ATTENTION',
            ]);
            $stats['attention_alert'] = 0;
            $stats['attention_alert_skip_reason'] = $decision['reason'];

            return $stats;
        }

        $stats['attention_alert'] = $this->sendPaidAttentionAlertOnce($fresh);
        $stats['attention_alert_skip_reason'] = null;
        $stats['attention_alert_reason'] = $decision['reason'];

        return $stats;
    }

    /**
     * @return array{sent: int, edited: int, recovered: int, skipped_stale: int}
     */
    public function publishClaimed(Task $task): array
    {
        return $this->publish($task, TelegramOrderMessagePresenter::LIFECYCLE_CLAIMED, allowSend: false, compactNew: false);
    }

    /**
     * @return array{sent: int, edited: int, recovered: int, skipped_stale: int}
     */
    public function publishCompleted(Task $task): array
    {
        return $this->publish($task, TelegramOrderMessagePresenter::LIFECYCLE_COMPLETED, allowSend: false, compactNew: false);
    }

    /**
     * @return array{sent: int, edited: int, recovered: int, skipped_stale: int}
     */
    public function publishCancelled(Task $task): array
    {
        return $this->publish($task, TelegramOrderMessagePresenter::LIFECYCLE_CANCELLED, allowSend: false, compactNew: false, allowRecover: true);
    }

    /**
     * @return array{sent: int, edited: int, recovered: int, skipped_stale: int}
     */
    public function publishRejected(Task $task): array
    {
        return $this->publish($task, TelegramOrderMessagePresenter::LIFECYCLE_REJECTED, allowSend: false, compactNew: false, allowRecover: true);
    }

    /**
     * @return array{sent: int, edited: int, recovered: int, skipped_stale: int}
     */
    public function publishExpired(Task $task): array
    {
        return $this->publish($task, TelegramOrderMessagePresenter::LIFECYCLE_EXPIRED, allowSend: false, compactNew: false, allowRecover: true);
    }

    /**
     * @return array{sent: int, edited: int, recovered: int, skipped_stale: int}
     */
    private function publish(
        Task $task,
        string $lifecycle,
        bool $allowSend,
        bool $compactNew,
        bool $allowRecover = false,
    ): array {
        $taskId = (int) $task->id;
        $stats = ['sent' => 0, 'edited' => 0, 'recovered' => 0, 'skipped_stale' => 0];
        $destinations = $this->destinations();
        if ($destinations === []) {
            $emptyClass = $this->classifyEmptyDestinations();
            Log::warning('telegram_order_lifecycle_no_destination', [
                'task_id' => $taskId,
                'lifecycle' => $lifecycle,
                'destination_empty_class' => $emptyClass,
            ]);
            if ($emptyClass === TelegramOperatorDestinationHealth::CLASS_AUTHORIZATION_EMPTY) {
                Log::error('telegram_order_lifecycle_destination_authorization_empty', [
                    'task_id' => $taskId,
                    'lifecycle' => $lifecycle,
                    'destination_empty_class' => $emptyClass,
                ]);
            } elseif ($emptyClass === TelegramOperatorDestinationHealth::CLASS_NO_CONFIGURATION) {
                Log::warning('telegram_order_lifecycle_destination_no_configuration', [
                    'task_id' => $taskId,
                    'lifecycle' => $lifecycle,
                    'destination_empty_class' => $emptyClass,
                ]);
            }

            return $stats;
        }

        $run = function () use ($task, $taskId, $lifecycle, $allowSend, $compactNew, $allowRecover, $destinations, &$stats): void {
            $fresh = $task->fresh() ?? $task;
            // Present from live DB so claim/paid both reflect payment + ownership.
            $payload = $this->presenter->present($fresh, $lifecycle);
            $operator = $this->nonEmptyString($payload['operator'] ?? null);
            $authoritative = TelegramOrderMessagePresenter::lifecycleFromStatus((int) ($fresh->status ?? 0), $operator);

            if ($this->shouldSkipAgainstAuthoritative($lifecycle, $authoritative)) {
                $stats['skipped_stale']++;
                $this->audit($fresh, $lifecycle, 'SKIP_STALE', null, null);

                return;
            }

            $text = $compactNew
                ? $this->presenter->renderCompactNew($payload)
                : $this->presenter->renderText($payload);
            $keyboard = $this->keyboardFor($lifecycle, $taskId, $operator !== null);
            $publicId = (string) ($fresh->public_id ?? $taskId);

            $existing = $this->registry->cards($taskId);
            $byChat = [];
            foreach ($existing as $card) {
                $byChat[(string) $card['chat_id']] = [
                    'message_id' => (int) $card['message_id'],
                    'lifecycle' => (string) ($card['lifecycle'] ?? TelegramOrderMessagePresenter::LIFECYCLE_NEW),
                ];
            }

            foreach ($destinations as $chatId) {
                $chatId = (string) $chatId;
                if ($chatId === '') {
                    continue;
                }

                $prior = $byChat[$chatId] ?? null;
                $messageId = $prior['message_id'] ?? null;
                $priorLife = (string) ($prior['lifecycle'] ?? '');

                if ($messageId !== null && $messageId > 0
                    && $this->shouldSkipAgainstPrior($lifecycle, $priorLife)
                ) {
                    $stats['skipped_stale']++;
                    $this->audit($fresh, $lifecycle, 'SKIP_STALE', $chatId, $messageId);
                    continue;
                }

                // Create-vs-paid race: never regress an advanced card back to compact NEW.
                if ($lifecycle === TelegramOrderMessagePresenter::LIFECYCLE_NEW
                    && $messageId !== null
                    && $messageId > 0
                    && $this->lifecycleRank($priorLife) > $this->lifecycleRank(TelegramOrderMessagePresenter::LIFECYCLE_NEW)
                ) {
                    $stats['skipped_stale']++;
                    continue;
                }

                if ($messageId !== null && $messageId > 0) {
                    $edit = $this->bot->editMessageResult($chatId, $messageId, $text, $keyboard);
                    if ($edit['ok']) {
                        $this->registry->remember($taskId, $chatId, $messageId, $lifecycle);
                        $stats['edited']++;
                        $this->audit($fresh, $lifecycle, 'EDIT', $chatId, $messageId);
                        continue;
                    }
                    if (! ($edit['message_missing'] ?? false)) {
                        Log::warning('telegram_order_lifecycle_edit_failed', [
                            'task_id' => $taskId,
                            'public_id' => $publicId,
                            'lifecycle' => $lifecycle,
                            'chat_id_suffix' => substr($chatId, -4),
                            'destination_empty_class' => TelegramOperatorDestinationHealth::CLASS_BOT_SEND_FAILURE,
                        ]);
                        continue;
                    }
                    if ($allowSend || $allowRecover || $lifecycle === TelegramOrderMessagePresenter::LIFECYCLE_PAID) {
                        $sent = $this->bot->sendMessageResult($chatId, $text, $keyboard);
                        if ($sent['ok'] && ($sent['message_id'] ?? 0) > 0) {
                            $newId = (int) $sent['message_id'];
                            $this->registry->remember($taskId, $chatId, $newId, $lifecycle);
                            $stats['recovered']++;
                            $this->audit($fresh, $lifecycle, 'RECOVER', $chatId, $newId);
                        }
                    }
                    continue;
                }

                if (! $allowSend && $lifecycle !== TelegramOrderMessagePresenter::LIFECYCLE_NEW) {
                    continue;
                }

                $sent = $this->bot->sendMessageResult($chatId, $text, $keyboard);
                if ($sent['ok'] && ($sent['message_id'] ?? 0) > 0) {
                    $newId = (int) $sent['message_id'];
                    $this->registry->remember($taskId, $chatId, $newId, $lifecycle);
                    $stats['sent']++;
                    $this->audit($fresh, $lifecycle, 'SEND', $chatId, $newId);
                } else {
                    Log::warning('telegram_order_lifecycle_send_failed', [
                        'task_id' => $taskId,
                        'public_id' => $publicId,
                        'lifecycle' => $lifecycle,
                        'chat_id_suffix' => substr($chatId, -4),
                        'destination_empty_class' => TelegramOperatorDestinationHealth::CLASS_BOT_SEND_FAILURE,
                    ]);
                }
            }
        };

        try {
            Cache::lock('telegram_order_card_lock:'.$taskId, 20)->block(15, $run);
        } catch (\Throwable $e) {
            Log::warning('telegram_order_lifecycle_lock_failed', [
                'task_id' => $taskId,
                'lifecycle' => $lifecycle,
                'exception_class' => $e::class,
            ]);
            $run();
        }

        Log::info('telegram_order_lifecycle_publish', [
            'task_id' => $taskId,
            'public_id' => (string) ($task->public_id ?? ''),
            'lifecycle' => $lifecycle,
            'sent' => $stats['sent'],
            'edited' => $stats['edited'],
            'recovered' => $stats['recovered'],
            'skipped_stale' => $stats['skipped_stale'],
        ]);

        return $stats;
    }

    /**
     * Exactly one short PAID attention alert per order (durable idempotency).
     * Dedup key ≈ order_id + event_type(PAID_ATTENTION) — not time-based.
     * Mutex + claim-before-send closes the race between queue retries / duplicate dispatch.
     */
    private function sendPaidAttentionAlertOnce(Task $task): int
    {
        $taskId = (int) $task->id;
        if ($taskId <= 0) {
            return 0;
        }

        $sent = 0;
        $run = function () use ($task, $taskId, &$sent): void {
            if ($this->registry->paidAttentionAlertAlreadySent($taskId)) {
                Log::info('telegram_order_card', [
                    'order' => (string) ($task->public_id ?? $taskId),
                    'task_id' => $taskId,
                    'state' => TelegramOrderMessagePresenter::LIFECYCLE_PAID,
                    'card_action' => null,
                    'attention_alert' => 'SKIP_DUP',
                    'attention_alert_result' => 'already_sent',
                    'message_id' => null,
                    'chat_id_suffix' => null,
                    'dedup_key' => 'order:'.$taskId.':PAID_ATTENTION',
                ]);

                return;
            }

            $destinations = $this->destinations();
            if ($destinations === []) {
                return;
            }

            // Claim slot before network I/O so a concurrent worker cannot double-send.
            $this->registry->markPaidAttentionAlertSent($taskId);

            $payload = $this->presenter->present($task, TelegramOrderMessagePresenter::LIFECYCLE_PAID);
            $text = $this->presenter->renderPaidAttentionAlert($payload);
            $cards = $this->registry->cards($taskId);
            $byChat = [];
            foreach ($cards as $card) {
                $byChat[(string) $card['chat_id']] = (int) $card['message_id'];
            }

            foreach ($destinations as $chatId) {
                $chatId = (string) $chatId;
                if ($chatId === '') {
                    continue;
                }
                $replyTo = $byChat[$chatId] ?? null;
                $result = $this->bot->sendMessageResult(
                    $chatId,
                    $text,
                    null,
                    ($replyTo !== null && $replyTo > 0) ? $replyTo : null
                );
                if ($result['ok']) {
                    $sent++;
                    Log::info('telegram_order_card', [
                        'order' => (string) ($task->public_id ?? $taskId),
                        'task_id' => $taskId,
                        'state' => TelegramOrderMessagePresenter::LIFECYCLE_PAID,
                        'card_action' => 'EDIT',
                        'attention_alert' => 'SEND',
                        'attention_alert_result' => 'success',
                        'message_id' => $replyTo,
                        'alert_message_id' => $result['message_id'] ?? null,
                        'chat_id_suffix' => substr($chatId, -4),
                        'dedup_key' => 'order:'.$taskId.':PAID_ATTENTION',
                    ]);
                } else {
                    Log::warning('telegram_order_card', [
                        'order' => (string) ($task->public_id ?? $taskId),
                        'task_id' => $taskId,
                        'state' => TelegramOrderMessagePresenter::LIFECYCLE_PAID,
                        'attention_alert' => 'SEND',
                        'attention_alert_result' => 'failed',
                        'message_id' => $replyTo,
                        'chat_id_suffix' => substr($chatId, -4),
                        'dedup_key' => 'order:'.$taskId.':PAID_ATTENTION',
                    ]);
                }
            }
        };

        try {
            Cache::lock('telegram_order_paid_alert_lock:'.$taskId, 20)->block(15, $run);
        } catch (\Throwable $e) {
            Log::warning('telegram_order_paid_alert_lock_failed', [
                'task_id' => $taskId,
                'exception_class' => $e::class,
            ]);
            $run();
        }

        return $sent;
    }

    private function shouldSkipAgainstAuthoritative(string $requested, string $authoritative): bool
    {
        if ($this->isOrthogonalActive($requested, $authoritative)) {
            return false;
        }
        // Claim ownership may update while payment status is already PAID.
        if ($requested === TelegramOrderMessagePresenter::LIFECYCLE_CLAIMED
            && $authoritative === TelegramOrderMessagePresenter::LIFECYCLE_PAID
        ) {
            return false;
        }
        // Paid event while DB still maps to claimed-only (status unchanged) is rare; allow when requested paid.
        if ($requested === TelegramOrderMessagePresenter::LIFECYCLE_PAID
            && $authoritative === TelegramOrderMessagePresenter::LIFECYCLE_CLAIMED
        ) {
            return false;
        }

        return $this->lifecycleRank($authoritative) > $this->lifecycleRank($requested);
    }

    private function shouldSkipAgainstPrior(string $requested, string $priorLife): bool
    {
        if ($priorLife === '' || $this->isOrthogonalActive($requested, $priorLife)) {
            return false;
        }

        return $this->lifecycleRank($priorLife) > $this->lifecycleRank($requested);
    }

    private function isOrthogonalActive(string $a, string $b): bool
    {
        $active = [
            TelegramOrderMessagePresenter::LIFECYCLE_PAID,
            TelegramOrderMessagePresenter::LIFECYCLE_CLAIMED,
        ];

        return in_array($a, $active, true) && in_array($b, $active, true);
    }

    private function nonEmptyString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trim = trim($value);

        return $trim !== '' ? $trim : null;
    }

    private function audit(Task $task, string $lifecycle, string $action, ?string $chatId, ?int $messageId): void
    {
        Log::info('telegram_order_card', [
            'order' => (string) ($task->public_id ?? $task->id ?? ''),
            'task_id' => (int) ($task->id ?? 0),
            'state' => $lifecycle,
            'action' => $action,
            'card_action' => $action,
            'message_id' => $messageId,
            'chat_id_suffix' => $chatId !== null ? substr($chatId, -4) : null,
        ]);
    }

    public function lifecycleRank(string $lifecycle): int
    {
        return match ($lifecycle) {
            TelegramOrderMessagePresenter::LIFECYCLE_NEW => 1,
            TelegramOrderMessagePresenter::LIFECYCLE_PAID,
            TelegramOrderMessagePresenter::LIFECYCLE_CLAIMED => 2,
            TelegramOrderMessagePresenter::LIFECYCLE_COMPLETED => 3,
            TelegramOrderMessagePresenter::LIFECYCLE_CANCELLED,
            TelegramOrderMessagePresenter::LIFECYCLE_REJECTED,
            TelegramOrderMessagePresenter::LIFECYCLE_EXPIRED => 4,
            default => 0,
        };
    }

    /**
     * Operational order cards are operator DMs only.
     * Channel id_channel rows remain for unrelated notifications (withdrawal, KYC card, etc.).
     *
     * @return list<string>
     */
    private function destinations(): array
    {
        // $this->selector retained for DI/compatibility; order-card channel path is retired.
        $out = [];
        $dmEnabled = filter_var(
            config('telegram_operator.notify_operator_dms', true),
            FILTER_VALIDATE_BOOLEAN
        );
        if ($dmEnabled) {
            // Test-only seam (never set in production env): skip Spatie/DB authorize.
            $force = config('telegram_operator.test_force_dm_ids');
            if (is_array($force) && $force !== [] && app()->environment('testing')) {
                foreach ($force as $uid) {
                    $out[] = (string) $uid;
                }
            } else {
                foreach ($this->auth->authorizedActionTelegramUserIds() as $uid) {
                    $out[] = (string) $uid;
                }
            }
        }

        return array_values(array_unique(array_filter($out, static fn ($v) => $v !== '')));
    }

    /**
     * Distinguish empty-destination causes for ops (never silent generic-only).
     */
    private function classifyEmptyDestinations(): string
    {
        $dmEnabled = filter_var(
            config('telegram_operator.notify_operator_dms', true),
            FILTER_VALIDATE_BOOLEAN
        );
        $links = $this->auth->links();
        // Do not re-enter authorizedActionTelegramUserIds heal loop here — use links + dm flag.
        // Authorization emptiness is already recorded by AuthService when applicable.
        return app(TelegramOperatorDestinationHealth::class)->classifyEmptyDestinations(
            $links,
            [],
            $dmEnabled
        );
    }

    /**
     * @return list<list<array{text: string, callback_data?: string, url?: string}>>|null
     */
    private function keyboardFor(string $lifecycle, int $taskId, bool $hasOperator): ?array
    {
        if (! filter_var(config('telegram_operator.actions_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        if (TelegramOrderMessagePresenter::isTerminalLifecycle($lifecycle)
            || $lifecycle === TelegramOrderMessagePresenter::LIFECYCLE_COMPLETED
        ) {
            return TelegramOrderOperatorWorkflowService::completedKeyboard($taskId);
        }

        // Ownership wins over payment for action buttons (no second "Принять").
        if ($hasOperator || $lifecycle === TelegramOrderMessagePresenter::LIFECYCLE_CLAIMED) {
            return TelegramOrderOperatorWorkflowService::claimedKeyboard($taskId);
        }

        return TelegramOrderOperatorWorkflowService::notificationKeyboard($taskId);
    }
}
