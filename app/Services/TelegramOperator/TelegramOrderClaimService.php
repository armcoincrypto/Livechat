<?php

declare(strict_types=1);

namespace App\Services\TelegramOperator;

use App\Models\OrderOperatorAssignment;
use App\Models\Task;
use App\Models\User;
use App\Services\Orders\ManualCompletion\ManualCompletionGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TelegramOrderClaimService
{
    public function __construct(
        private readonly TelegramOperatorAuthService $auth,
        private readonly TelegramOperatorAuditLogger $audit,
    ) {
    }

    /**
     * @return array{ok: bool, code: string, message: string, assignment?: OrderOperatorAssignment}
     */
    public function claim(int $taskId, User $operator, int $telegramUserId): array
    {
        try {
            return DB::transaction(function () use ($taskId, $operator, $telegramUserId) {
                $task = Task::query()->whereKey($taskId)->lockForUpdate()->first();
                if ($task === null) {
                    $this->audit->log('ORDER_TELEGRAM_TAKE', $taskId, $operator, $telegramUserId, [
                        'result' => 'rejected',
                        'reason' => 'not_found',
                    ]);

                    return ['ok' => false, 'code' => 'not_found', 'message' => 'Заявка не найдена'];
                }

                if ((int) $task->status === ManualCompletionGuard::COMPLETED) {
                    $this->audit->log('ORDER_TELEGRAM_TAKE', $taskId, $operator, $telegramUserId, [
                        'result' => 'rejected',
                        'reason' => 'already_completed',
                        'old_status' => (int) $task->status,
                    ]);

                    return ['ok' => false, 'code' => 'already_completed', 'message' => '✅ Заявка уже выполнена'];
                }

                $existing = OrderOperatorAssignment::query()
                    ->where('task_id', $taskId)
                    ->whereNull('released_at')
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    if ((int) $existing->operator_user_id === (int) $operator->id) {
                        $this->audit->log('ORDER_TELEGRAM_TAKE', $taskId, $operator, $telegramUserId, [
                            'result' => 'idempotent',
                            'reason' => 'already_claimed_by_self',
                        ]);

                        return [
                            'ok' => true,
                            'code' => 'already_yours',
                            'message' => 'Заявка уже принята вами',
                            'assignment' => $existing,
                        ];
                    }

                    $holder = User::query()->find($existing->operator_user_id);
                    $holderName = $holder ? $this->auth->displayName($holder) : 'другим оператором';
                    $this->audit->log('ORDER_TELEGRAM_TAKE', $taskId, $operator, $telegramUserId, [
                        'result' => 'conflict',
                        'claimed_by' => (int) $existing->operator_user_id,
                    ]);

                    return [
                        'ok' => false,
                        'code' => 'already_claimed',
                        'message' => '⚠️ Заявка уже принята оператором '.$holderName,
                    ];
                }

                $assignment = OrderOperatorAssignment::query()->create([
                    'task_id' => $taskId,
                    'operator_user_id' => (int) $operator->id,
                    'telegram_user_id' => $telegramUserId,
                    'source' => 'telegram',
                    'claimed_at' => now(),
                ]);

                $this->audit->log('ORDER_TELEGRAM_TAKE', $taskId, $operator, $telegramUserId, [
                    'result' => 'ok',
                    'old_status' => (int) $task->status,
                    'new_status' => (int) $task->status,
                    'note' => 'claim_only_no_financial_status_change',
                ]);

                return [
                    'ok' => true,
                    'code' => 'claimed',
                    'message' => '👤 В работе у: '.$this->auth->displayName($operator),
                    'assignment' => $assignment,
                ];
            });
        } catch (Throwable $e) {
            Log::error('telegram_operator_claim_failed', [
                'task_id' => $taskId,
                'operator_id' => (int) $operator->id,
                'exception' => $e::class,
            ]);

            return ['ok' => false, 'code' => 'error', 'message' => 'Не удалось принять заявку'];
        }
    }

    public function activeAssignment(int $taskId): ?OrderOperatorAssignment
    {
        return OrderOperatorAssignment::query()
            ->where('task_id', $taskId)
            ->whereNull('released_at')
            ->first();
    }
}
