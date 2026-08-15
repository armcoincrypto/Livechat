<?php

namespace App\Http\Controllers\Administrator\Orders;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Orders\OrderBonusesResources;
use App\Models\Reserve;
use App\Enums\ReserveLedgerAction;
use App\Enums\ReserveLedgerSource;
use App\Models\ReserveLedger;
use Illuminate\Support\Carbon;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use App\Models\UserBalance;
use App\Models\WithdrawalRequest;
use App\Models\WithdrawalRequestLog;
use iEXPackages\SmartMailer\Facades\SmartMailer;
use Illuminate\Http\Request;

class PaymentBonusesController extends Controller
{
    /**
     * Дополнительные фильтры
     */
    protected array $allowFiltered = [
        'payment_bonuses_paginate',
    ];

    /**
     * Список выплат
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $withdrawal = WithdrawalRequest::with([
            'manager', 'currency', 'currency.payment', 'currency.code_currency', 'user',
        ])->filter($request->all())->orderByDesc('id')
            ->paginate((int) iEXSetting('payment_bonuses_paginate'));

        return response()->json([
            'items' => new OrderBonusesResources($withdrawal)
        ]);
    }

    public function update(int $id, Request $request)
    {
        $action = $request->input('actions');

        if (!in_array($action, ['success', 'failed'])) {
            return response()->json([
                'status' => 1,
                'message' => 'Некорректное или отсутствующее действие.'
            ]);
        }

        /** @var WithdrawalRequest $withdrawal */
        $withdrawal = WithdrawalRequest::with(['currency.payment', 'currency.code_currency'])
            ->findOrFail($id);

        if ($withdrawal->status === 1) {
            return response()->json([
                'status' => 1,
                'message' => 'Заявка уже была обработана ранее.'
            ]);
        }

        $userBalance = UserBalance::where('id_user', $withdrawal->id_user)->first();
        if (!$userBalance) {
            return response()->json([
                'status' => 1,
                'message' => 'Баланс пользователя не найден.'
            ]);
        }


        if ($action === 'success') {

            $reserve = Reserve::where('id_currency', $withdrawal->id_currency)->first();
            if (!$reserve) {
                return response()->json([
                    'status' => 1,
                    'message' => 'Не найден резерв для валюты.'
                ]);
            }

            $scale = (int) ($reserve->currency->number_format ?? 18);
            $scale = max(0, min($scale, 18));

            $withdrawalAmount = iex_money_normalize($withdrawal->balance_referral, $scale);
            $baseReferral = iex_money_normalize($withdrawal->base_referral, $scale);

            // Записываем лог успешной выплаты
            WithdrawalRequestLog::create([
                'id_withdrawal_request' => $withdrawal->id,
                'text' => sprintf(
                    'Средства успешно выплачены на %s %s. Сумма: %s %s',
                    $withdrawal->currency->payment->name,
                    $withdrawal->currency->code_currency->name,
                    $withdrawalAmount,
                    $withdrawal->currency->code_currency->name
                ),
                'amount' => $withdrawalAmount,
                'remainder' => 0,
                'id_manager' => auth()->id(),
            ]);

            // Обновляем баланс пользователя
            $userBalance->update([
                'referral_total_withdrawal' => $userBalance->referral_total_withdrawal + $baseReferral,
            ]);

            // Обновляем резерв валюты с использованием BigDecimal и нового ledger
            try {
                $beforeDec = BigDecimal::of(iex_money_normalize((string) $reserve->summa, $scale));
                $amountDec = BigDecimal::of($withdrawalAmount);
                $afterDec = $beforeDec->minus($amountDec);
                $newReserveSum = $afterDec->toScale($scale, RoundingMode::DOWN)->__toString();
            } catch (\Throwable $e) {
                return response()->json([
                    'status' => 1,
                    'message' => 'Ошибка при вычислении нового резерва: ' . $e->getMessage()
                ]);
            }

            // Ledger: фиксируем ручное уменьшение резерва при выплате партнёрского бонуса
            $deltaSigned = $amountDec->negated()->toScale($scale, RoundingMode::DOWN)->__toString();
            $idempotencyKey = 'admin_withdrawal_bonus:' . (int) $withdrawal->id . ':r' . (int) $reserve->id;

            ReserveLedger::updateOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'reserve_id' => (int) $reserve->id,
                    'direction_exchange_id' => null,
                    'task_id' => null,
                    'currency_id' => (int) $withdrawal->id_currency,

                    'action' => ReserveLedgerAction::MANUAL_ADJUST->value,
                    'source_type' => ReserveLedgerSource::ADMIN->value,
                    'source_id' => (int) (auth()->id() ?? 0),

                    'delta' => $deltaSigned,
                    'balance_before' => $beforeDec->__toString(),
                    'balance_after' => $newReserveSum,

                    'meta' => [
                        'context' => 'withdrawal_bonus_success',
                        'withdrawal_request_id' => (int) $withdrawal->id,
                        'user_id' => (int) $withdrawal->id_user,
                        'payment_system' => (string) ($withdrawal->currency?->payment?->name ?? ''),
                        'currency_code' => (string) ($withdrawal->currency?->code_currency?->name ?? ''),
                        'scale' => $scale,
                    ],
                    'occurred_at' => Carbon::now(),
                ]
            );

            $reserve->update(['summa' => $newReserveSum]);

            // Устанавливаем статус заявки как выполненный
            $withdrawal->update([
                'id_manager' => auth()->id(),
                'status' => 1,
                'balance_referral' => 0,
            ]);

            // Отправка уведомления о выплате партнёру (если включена)
            if ((int)iEXSetting('is_mail_order_payout_success') === 1) {
                SmartMailer::dispatch(
                    sendable: 'user_withdrawal_bonus_completed_job',
                    model: $withdrawal,
                    delaySeconds: 20,
                    queue: 'low'
                );
            }

            return response()->json([
                'status' => 0,
                'message' => 'Заявка успешно выполнена.'
            ]);
        }

        if ($action === 'failed') {

            // Возвращаем средства пользователю
            $userBalance->update([
                'balance' => iex_number_format($userBalance->balance + $withdrawal->base_referral),
            ]);

            $withdrawal->delete();

            return response()->json([
                'status' => 0,
                'message' => 'Заявка отклонена. Средства возвращены на баланс пользователя.'
            ]);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Неизвестное действие.'
        ]);
    }
}
