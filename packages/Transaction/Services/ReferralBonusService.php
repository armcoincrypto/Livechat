<?php

namespace iEXPackages\Transaction\Services;

use App\Models\Task;
use iEXPackages\ReferralSystem\ReferralSystemFacade;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReferralBonusService
{
    /**
     * Обработка начисления реферального бонуса.
     */
    public function process(?Task $task = null): void
    {
        if (!$task) {
            return;
        }

        // 0 = ещё не обрабатывали, есть referral_hash
        if ((int) $task->is_pay_referral_bonus !== 0 || empty($task->referral_hash)) {
            return;
        }

        try {
            $result = ReferralSystemFacade::creditForTask($task);

            if ($result->credited) {
                /**
                 * ВАЖНО:
                 * Если у тебя включён HOLD, начисление может быть "в ожидании" (pending).
                 * Тогда ставить is_pay_referral_bonus=1 нельзя, иначе ты закроешь обработку раньше времени.
                 *
                 * Поэтому:
                 * - либо ставь 1 только когда начисление confirmed,
                 * - либо введи отдельное значение, например 2 = pending.
                 *
                 * Ниже безопасный вариант: ставим 1, только если HOLD выключен.
                 */
                $holdDays = (int) iEXSetting('referral_hold_days', 0);

                if ($holdDays > 0) {
                    // pending
                    $task->update(['is_pay_referral_bonus' => 2]); // 2 = начислено, но в HOLD
                } else {
                    // confirmed
                    $task->update(['is_pay_referral_bonus' => 1]);
                }
            } else {
                Log::info('Referral bonus skipped', [
                    'task_id' => $task->id,
                    'reason'  => $result->message,
                ]);
            }
        } catch (Throwable $e) {
            Log::error('Referral bonus error: ' . $e->getMessage(), [
                'task_id' => $task->id,
            ]);
        }
    }
}
