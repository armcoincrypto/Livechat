<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual;

use App\Models\User;
use App\Models\UserBalance;
use App\Models\UserBalanceLog;
use Illuminate\Support\Facades\DB;

/**
 * Minimal serialization wrapper for admin absolute balance SET.
 * Does not change UX; adds row locking so accrual deltas cannot race silently.
 */
final class AdminPartnerBalanceAbsoluteSet
{
    /**
     * @return array{changed: bool, from: string, to: string, audit_log_id: int|null, message: string}
     */
    public function setAbsolute(int $userId, string $newBalanceDecimal, int $managerId): array
    {
        return DB::transaction(function () use ($userId, $newBalanceDecimal, $managerId) {
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $newBalance = AccrualMoney::of($newBalanceDecimal);

            $balance = UserBalance::query()
                ->where('id_user', $userId)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                $balance = UserBalance::create([
                    'id_user' => $userId,
                    'id_code_currency' => (int) (function_exists('iEXSetting') ? iEXSetting('id_referral_code_currency') : 8),
                    'balance' => AccrualMoney::toLegacyBalanceScalar($newBalance),
                    'referral_total_profit' => 0,
                    'referral_total_withdrawal' => 0,
                ]);
                $from = AccrualMoney::zero();
                $type = 1;
                $logText = 'Установлен начальный баланс: '.AccrualMoney::display2($newBalance);
            } else {
                $from = AccrualMoney::fromLegacyBalance($balance->balance);
                if (AccrualMoney::eq($from, $newBalance)) {
                    return [
                        'changed' => false,
                        'from' => AccrualMoney::scale8($from),
                        'to' => AccrualMoney::scale8($newBalance),
                        'audit_log_id' => null,
                        'message' => 'Баланс не изменился',
                    ];
                }
                $type = AccrualMoney::cmp($newBalance, $from) > 0 ? 1 : 0;
                $action = $type ? 'увеличен' : 'уменьшен';
                $logText = "Реферальный бонус {$action} с ".AccrualMoney::display2($from).' до '.AccrualMoney::display2($newBalance);
                $balance->balance = AccrualMoney::toLegacyBalanceScalar($newBalance);
                $balance->save();
            }

            $audit = UserBalanceLog::create([
                'id_manager' => $managerId,
                'id_user' => $user->id,
                'route_type' => 1,
                'type' => $type,
                'source' => 'admin_absolute_set',
                'reference' => null,
                'text' => $logText,
                'from_balance' => AccrualMoney::scale8($from),
                'to_balance' => AccrualMoney::scale8($newBalance),
            ]);

            return [
                'changed' => true,
                'from' => AccrualMoney::scale8($from),
                'to' => AccrualMoney::scale8($newBalance),
                'audit_log_id' => (int) $audit->id,
                'message' => 'Баланс успешно обновлён',
            ];
        });
    }
}
