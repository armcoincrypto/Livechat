<?php

declare(strict_types=1);

namespace App\Services\TelegramOperator;

use App\Models\Task;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Authoritative operational Telegram card identity per order destination.
 * One order may have one card per destination chat (channel and/or operator DMs).
 */
final class TelegramOrderCardRegistry
{
    private const CACHE_TTL_DAYS = 30;

    /**
     * @return list<array{chat_id: string, message_id: int, lifecycle: string}>
     */
    public function cards(int $taskId): array
    {
        $fromCache = Cache::get($this->cacheKey($taskId));
        if (is_array($fromCache) && $fromCache !== []) {
            return $this->normalizeList($fromCache);
        }

        try {
            $task = Task::query()->find($taskId);
            $opt = is_array($task?->opt_params) ? $task->opt_params : [];
            $stored = $opt['telegram_operator_card']['destinations'] ?? null;
            if (is_array($stored) && $stored !== []) {
                Cache::put($this->cacheKey($taskId), $stored, now()->addDays(self::CACHE_TTL_DAYS));

                return $this->normalizeList($stored);
            }
        } catch (Throwable $e) {
            Log::warning('telegram_order_card_registry_read_failed', [
                'task_id' => $taskId,
                'exception_class' => $e::class,
            ]);
        }

        return [];
    }

    public function remember(int $taskId, string $chatId, int $messageId, string $lifecycle): void
    {
        if ($taskId <= 0 || $chatId === '' || $messageId <= 0) {
            return;
        }

        $cards = $this->cards($taskId);
        $found = false;
        foreach ($cards as &$card) {
            if ((string) $card['chat_id'] === $chatId) {
                // Never regress lifecycle on the same destination card.
                $prevRank = $this->lifecycleRank((string) ($card['lifecycle'] ?? ''));
                $nextRank = $this->lifecycleRank($lifecycle);
                $card['message_id'] = $messageId;
                if ($nextRank >= $prevRank) {
                    $card['lifecycle'] = $lifecycle;
                }
                $found = true;
                break;
            }
        }
        unset($card);
        if (! $found) {
            $cards[] = [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'lifecycle' => $lifecycle,
            ];
        }

        Cache::put($this->cacheKey($taskId), $cards, now()->addDays(self::CACHE_TTL_DAYS));

        try {
            $task = Task::query()->find($taskId);
            if ($task === null) {
                return;
            }
            $opt = is_array($task->opt_params) ? $task->opt_params : [];
            $opt['telegram_operator_card'] = [
                'destinations' => $cards,
                'updated_at' => now()->toIso8601String(),
            ];
            $task->opt_params = $opt;
            $task->save();
        } catch (Throwable $e) {
            Log::warning('telegram_order_card_registry_persist_failed', [
                'task_id' => $taskId,
                'exception_class' => $e::class,
            ]);
        }
    }

    private function cacheKey(int $taskId): string
    {
        return 'telegram_order_card:'.$taskId;
    }

    /**
     * @param  mixed  $raw
     * @return list<array{chat_id: string, message_id: int, lifecycle: string}>
     */
    private function normalizeList(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $chat = isset($row['chat_id']) ? (string) $row['chat_id'] : '';
            $msg = isset($row['message_id']) ? (int) $row['message_id'] : 0;
            if ($chat === '' || $msg <= 0) {
                continue;
            }
            $out[] = [
                'chat_id' => $chat,
                'message_id' => $msg,
                'lifecycle' => (string) ($row['lifecycle'] ?? 'new'),
            ];
        }

        return $out;
    }

    private function lifecycleRank(string $lifecycle): int
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
}
