<?php

declare(strict_types=1);

namespace App\Services\TelegramOperator;

use App\Models\Task;
use App\Services\Orders\TelegramNotificationSelector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * One operational Telegram card per order destination.
 * NEW = compact SEND; later lifecycle states EDIT the stored message.
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
     * Initial compact NEW card (SEND) for all configured destinations.
     *
     * @return array{sent: int, edited: int, recovered: int}
     */
    public function publishNew(Task $task): array
    {
        return $this->publish($task, TelegramOrderMessagePresenter::LIFECYCLE_NEW, allowSend: true, compactNew: true);
    }

    /**
     * Customer-marked-paid / operator confirmation: EDIT existing cards.
     * Sends a replacement only when the stored message is genuinely missing.
     *
     * @return array{sent: int, edited: int, recovered: int}
     */
    public function publishPaid(Task $task): array
    {
        return $this->publish($task, TelegramOrderMessagePresenter::LIFECYCLE_PAID, allowSend: true, compactNew: false);
    }

    /**
     * @return array{sent: int, edited: int, recovered: int}
     */
    public function publishClaimed(Task $task): array
    {
        return $this->publish($task, TelegramOrderMessagePresenter::LIFECYCLE_CLAIMED, allowSend: false, compactNew: false);
    }

    /**
     * @return array{sent: int, edited: int, recovered: int}
     */
    public function publishCompleted(Task $task): array
    {
        return $this->publish($task, TelegramOrderMessagePresenter::LIFECYCLE_COMPLETED, allowSend: false, compactNew: false);
    }

    /**
     * @return array{sent: int, edited: int, recovered: int}
     */
    private function publish(Task $task, string $lifecycle, bool $allowSend, bool $compactNew): array
    {
        $taskId = (int) $task->id;
        $stats = ['sent' => 0, 'edited' => 0, 'recovered' => 0];
        $destinations = $this->destinations();
        if ($destinations === []) {
            Log::warning('telegram_order_lifecycle_no_destination', [
                'task_id' => $taskId,
                'lifecycle' => $lifecycle,
            ]);

            return $stats;
        }

        $run = function () use ($task, $taskId, $lifecycle, $allowSend, $compactNew, $destinations, &$stats): void {
            $payload = $this->presenter->present($task, $lifecycle);
            $text = $compactNew
                ? $this->presenter->renderCompactNew($payload)
                : $this->presenter->renderText($payload);
            $keyboard = $this->keyboardFor($lifecycle, $taskId);

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

                // Create-vs-paid race: never regress an advanced card back to compact NEW.
                if ($lifecycle === TelegramOrderMessagePresenter::LIFECYCLE_NEW
                    && $messageId !== null
                    && $messageId > 0
                    && $this->lifecycleRank($priorLife) > $this->lifecycleRank(TelegramOrderMessagePresenter::LIFECYCLE_NEW)
                ) {
                    continue;
                }

                if ($messageId !== null && $messageId > 0) {
                    $edit = $this->bot->editMessageResult($chatId, $messageId, $text, $keyboard);
                    if ($edit['ok']) {
                        $this->registry->remember($taskId, $chatId, $messageId, $lifecycle);
                        $stats['edited']++;
                        continue;
                    }
                    if (! ($edit['message_missing'] ?? false)) {
                        // Transient edit failure — do not spawn a duplicate card.
                        Log::warning('telegram_order_lifecycle_edit_failed', [
                            'task_id' => $taskId,
                            'lifecycle' => $lifecycle,
                            'chat_id_suffix' => substr($chatId, -4),
                        ]);
                        continue;
                    }
                    // Explicit missing message → one recovery send.
                    if ($allowSend || $lifecycle === TelegramOrderMessagePresenter::LIFECYCLE_PAID) {
                        $sent = $this->bot->sendMessageResult($chatId, $text, $keyboard);
                        if ($sent['ok'] && ($sent['message_id'] ?? 0) > 0) {
                            $this->registry->remember($taskId, $chatId, (int) $sent['message_id'], $lifecycle);
                            $stats['recovered']++;
                        }
                    }
                    continue;
                }

                if (! $allowSend && $lifecycle !== TelegramOrderMessagePresenter::LIFECYCLE_NEW) {
                    // No card yet and this transition should not create one (claimed/completed without prior notify).
                    continue;
                }

                // NEW or PAID-without-prior-card: send once (paid-first race still yields one card).
                $sent = $this->bot->sendMessageResult($chatId, $text, $keyboard);
                if ($sent['ok'] && ($sent['message_id'] ?? 0) > 0) {
                    $this->registry->remember($taskId, $chatId, (int) $sent['message_id'], $lifecycle);
                    $stats['sent']++;
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
            'lifecycle' => $lifecycle,
            'sent' => $stats['sent'],
            'edited' => $stats['edited'],
            'recovered' => $stats['recovered'],
        ]);

        return $stats;
    }

    private function lifecycleRank(string $lifecycle): int
    {
        return match ($lifecycle) {
            TelegramOrderMessagePresenter::LIFECYCLE_NEW => 1,
            TelegramOrderMessagePresenter::LIFECYCLE_PAID => 2,
            TelegramOrderMessagePresenter::LIFECYCLE_CLAIMED => 3,
            TelegramOrderMessagePresenter::LIFECYCLE_COMPLETED => 4,
            default => 0,
        };
    }

    /**
     * @return list<string>
     */
    private function destinations(): array
    {
        $out = [];
        foreach ($this->selector->enabledForEvent('process_created_order') as $row) {
            $channel = trim((string) ($row->id_channel ?? ''));
            if ($channel !== '') {
                $out[] = $channel;
            }
        }
        // Also destinations flagged for new_order_for_operator (paid path historically).
        foreach ($this->selector->enabledForEvent('new_order_for_operator') as $row) {
            $channel = trim((string) ($row->id_channel ?? ''));
            if ($channel !== '') {
                $out[] = $channel;
            }
        }

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
            TelegramOrderMessagePresenter::LIFECYCLE_COMPLETED => TelegramOrderOperatorWorkflowService::completedKeyboard($taskId),
            default => TelegramOrderOperatorWorkflowService::notificationKeyboard($taskId),
        };
    }
}
