<?php

declare(strict_types=1);

namespace App\Services\TelegramOperator;

use Illuminate\Support\Facades\Cache;

/**
 * Multi-step complete flow state (evidence → confirm). Not authoritative financial state.
 *
 * @phpstan-type Flow array{
 *   stage: string,
 *   task_id: int,
 *   chat_id: int|string,
 *   message_id: int|null,
 *   settlement_reference?: string,
 *   operator_user_id: int
 * }
 */
final class TelegramOperatorPendingFlow
{
    private function key(int $telegramUserId): string
    {
        return 'tg_op_flow:'.$telegramUserId;
    }

    public function put(int $telegramUserId, array $flow): void
    {
        $ttl = (int) config('telegram_operator.pending_ttl_minutes', 30);
        Cache::put($this->key($telegramUserId), $flow, now()->addMinutes(max(5, $ttl)));
    }

    public function get(int $telegramUserId): ?array
    {
        $flow = Cache::get($this->key($telegramUserId));

        return is_array($flow) ? $flow : null;
    }

    public function clear(int $telegramUserId): void
    {
        Cache::forget($this->key($telegramUserId));
    }
}
