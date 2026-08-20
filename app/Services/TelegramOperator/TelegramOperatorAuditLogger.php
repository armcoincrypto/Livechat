<?php

declare(strict_types=1);

namespace App\Services\TelegramOperator;

use App\Models\User;
use Illuminate\Support\Facades\Log;

final class TelegramOperatorAuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log(string $action, ?int $taskId, ?User $operator, ?int $telegramUserId, array $context = []): void
    {
        $safe = [
            'action' => $action,
            'task_id' => $taskId,
            'operator_id' => $operator ? (int) $operator->id : null,
            'telegram_user_id' => $telegramUserId,
            'timestamp' => now()->toIso8601String(),
        ];

        foreach (['old_status', 'new_status', 'result', 'reason', 'claimed_by', 'note', 'dry_run', 'settlement_reference_len'] as $key) {
            if (array_key_exists($key, $context)) {
                $safe[$key] = $context[$key];
            }
        }

        // Never log raw settlement reference / secrets / tokens
        Log::info('telegram_operator_audit', $safe);
    }
}
