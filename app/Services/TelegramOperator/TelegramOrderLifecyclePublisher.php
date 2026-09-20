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
     * @return array{sent: int, edited: int, recovered: int, skipped_stale: int}
     */
    public function publishNew(Task $task): array
    {
        return $this->publish($task, TelegramOrderMessagePresenter::LIFECYCLE_NEW, allowSend: true, compactNew: true);
    }

    /**
     * @return array{sent: int, edited: int, recovered: int, skipped_stale: int}
     */
    public function publishPaid(Task $task): array
    {
        return $this->publish($task, TelegramOrderMessagePresenter::LIFECYCLE_PAID, allowSend: true, compactNew: false);
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
            Log::warning('telegram_order_lifecycle_no_destination', [
                'task_id' => $taskId,
                'lifecycle' => $lifecycle,
            ]);

            return $stats;
        }

        $run = function () use ($task, $taskId, $lifecycle, $allowSend, $compactNew, $allowRecover, $destinations, &$stats): void {
            $fresh = $task->fresh() ?? $task;
            $authoritative = TelegramOrderMessagePresenter::lifecycleFromStatus((int) ($fresh->status ?? 0));
            // Delayed older jobs must not regress the card past the live status.
            if ($this->lifecycleRank($authoritative) > $this->lifecycleRank($lifecycle)
                && ! ($lifecycle === TelegramOrderMessagePresenter::LIFECYCLE_CLAIMED
                    && $authoritative === TelegramOrderMessagePresenter::LIFECYCLE_PAID)
            ) {
                $stats['skipped_stale']++;
                $this->audit($fresh, $lifecycle, 'SKIP_STALE', null, null);

                return;
            }

            $payload = $this->presenter->present($fresh, $lifecycle);
            $text = $compactNew
                ? $this->presenter->renderCompactNew($payload)
                : $this->presenter->renderText($payload);
            $keyboard = $this->keyboardFor($lifecycle, $taskId);
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
                    && $this->lifecycleRank($priorLife) > $this->lifecycleRank($lifecycle)
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

    private function audit(Task $task, string $lifecycle, string $action, ?string $chatId, ?int $messageId): void
    {
        Log::info('telegram_order_card', [
            'order' => (string) ($task->public_id ?? $task->id ?? ''),
            'task_id' => (int) ($task->id ?? 0),
            'state' => $lifecycle,
            'action' => $action,
            'message_id' => $messageId,
            'chat_id_suffix' => $chatId !== null ? substr($chatId, -4) : null,
        ]);
    }

    public function lifecycleRank(string $lifecycle): int
    {
        return match ($lifecycle) {
            TelegramOrderMessagePresenter::LIFECYCLE_NEW => 1,
            TelegramOrderMessagePresenter::LIFECYCLE_PAID => 2,
            TelegramOrderMessagePresenter::LIFECYCLE_CLAIMED => 3,
            TelegramOrderMessagePresenter::LIFECYCLE_COMPLETED => 4,
            TelegramOrderMessagePresenter::LIFECYCLE_CANCELLED,
            TelegramOrderMessagePresenter::LIFECYCLE_REJECTED,
            TelegramOrderMessagePresenter::LIFECYCLE_EXPIRED => 5,
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
            foreach ($this->auth->authorizedActionTelegramUserIds() as $uid) {
                $out[] = (string) $uid;
            }
        }

        return array_values(array_unique(array_filter($out, static fn ($v) => $v !== '')));
    }

    /**
     * @return list<list<array{text: string, callback_data?: string, url?: string}>>|null
     */
    private function keyboardFor(string $lifecycle, int $taskId): ?array
    {
        if (! filter_var(config('telegram_operator.actions_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        return match ($lifecycle) {
            TelegramOrderMessagePresenter::LIFECYCLE_CLAIMED => TelegramOrderOperatorWorkflowService::claimedKeyboard($taskId),
            TelegramOrderMessagePresenter::LIFECYCLE_COMPLETED,
            TelegramOrderMessagePresenter::LIFECYCLE_CANCELLED,
            TelegramOrderMessagePresenter::LIFECYCLE_REJECTED,
            TelegramOrderMessagePresenter::LIFECYCLE_EXPIRED => TelegramOrderOperatorWorkflowService::completedKeyboard($taskId),
            default => TelegramOrderOperatorWorkflowService::notificationKeyboard($taskId),
        };
    }
}
