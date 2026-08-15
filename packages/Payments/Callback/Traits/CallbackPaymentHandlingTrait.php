<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Callback\Traits;

use App\Jobs\AdminNewOrderJob;
use App\Models\GatewayMerchant;
use App\Models\MerchantTransactionData;
use App\Models\MerchantTransactionWebhook;
use App\Models\Task;
use iEXPackages\Payments\Callback\DTO\CallbackHttpResult;
use iEXPackages\Payments\Core\Config\GatewayConfig;
use iEXPackages\Payments\Payments;
use iEXPackages\Transaction\Facades\TransactionFacade;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Psr\SimpleCache\InvalidArgumentException;

trait CallbackPaymentHandlingTrait
{
    /**
     * @param GatewayConfig $gatewayConfig
     * @param array<string,mixed> $callbackConfig
     * @param Task $task
     * @param GatewayMerchant $merchant
     * @param MerchantTransactionData|null $merchantTxData
     * @param array<string,mixed> $payload
     * @param string $ip
     * @param string $userAgent
     * @return CallbackHttpResult
     * @throws InvalidArgumentException
     * @throws \Throwable
     */
    private function processPaymentCallback(
        GatewayConfig $gatewayConfig,
        array $callbackConfig,
        Task $task,
        GatewayMerchant $merchant,
        ?MerchantTransactionData $merchantTxData,
        array $payload,
        string $ip,
        string $userAgent
    ): CallbackHttpResult {

        // фиксируем, что уведомление получено
        $task->update(['started_at' => Carbon::now()]);

        $transaction = TransactionFacade::init($task);

        $this->flowLogger->info(
            event: 'callback_processing_started',
            task: $task,
            merchant: $merchant,
            ctx: [
                'ip' => $ip,
                'user_agent' => $userAgent,
                'context' => [
                    'payload' => $this->payloadPreview($payload),
                ],
            ],
            message: 'Начата проверка оплаты по уведомлению.',
            stage: 'process',
            flow: 'callback'
        );

        $gateway = Payments::forMerchant($merchant);

        // выполняем complete_purchase (подпись и валидации — внутри Response конкретного шлюза)
        $completeRequest = $gateway->request('complete_purchase', $payload)->withTask($task);
        $paymentResponse = $completeRequest->send();

        // валюта (если шлюз отдаёт)
        if (method_exists($paymentResponse, 'getCurrency')) {
            $currency = (string)($paymentResponse->getCurrency() ?? '');
            if ($currency !== '' && Str::upper($currency) !== Str::upper((string)$transaction->getCodeIn()->name)) {
                $this->flowLogger->security(
                    event: 'callback_blocked_wrong_currency',
                    task: $task,
                    merchant: $merchant,
                    ctx: ['ip' => $ip, 'context' => ['got' => $currency, 'expected' => (string)$transaction->getCodeIn()->name]],
                    message: 'Оплата отклонена: валюта не совпадает с заявкой.',
                    stage: 'security',
                    flow: 'callback'
                );

                $transaction->setCategoryReject(4)->reject();
                return new CallbackHttpResult(200, 'OK');
            }
        }

        // pending / cancelled
        if (method_exists($paymentResponse, 'isPending') && $paymentResponse->isPending()) {
            $task->task_info?->update(['is_pending' => 1]);

            $this->flowLogger->info(
                event: 'callback_payment_pending',
                task: $task,
                merchant: $merchant,
                ctx: [
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                    'context' => [
                        'payload' => $this->payloadPreview($payload),
                    ],
                ],
                message: 'Оплата ещё не завершена. Ожидаем подтверждение.',
                stage: 'result',
                flow: 'callback'
            );

            return new CallbackHttpResult(200, 'OK');
        }


        if (method_exists($paymentResponse, 'isCancelled') && $paymentResponse->isCancelled()) {
            $task->task_info?->update(['is_pending' => 0]);

            $this->flowLogger->warning(
                event: 'callback_payment_cancelled',
                task: $task,
                merchant: $merchant,
                ctx: [
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                    'context' => [
                        'payload' => $this->payloadPreview($payload),
                    ],
                ],
                message: 'Оплата отменена или завершилась ошибкой.',
                stage: 'result',
                flow: 'callback'
            );

            return new CallbackHttpResult(200, 'OK');
        }

        // успех
        if (!method_exists($paymentResponse, 'isSuccessful') || !$paymentResponse->isSuccessful()) {
            $this->flowLogger->warning(
                event: 'callback_payment_unknown_result',
                task: $task,
                merchant: $merchant,
                ctx: [
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                    'context' => [
                        'payload' => $this->payloadPreview($payload),
                    ],
                ],
                message: 'Платежный сервис не подтвердил оплату (результат не является успешным).',
                stage: 'result',
                flow: 'callback'
            );

            return new CallbackHttpResult(200, 'OK');
        }

        $task->task_info?->update(['is_pending' => 0]);

        // сумма
        $paidAmount = method_exists($paymentResponse, 'getAmount') ? (float)$paymentResponse->getAmount() : 0.0;

        if ($paidAmount > 0 && empty($task->in_amount_merchant)) {
            $task->update(['in_amount_merchant' => $paidAmount]);
        }

        // лог webhook (если есть)
        if (
            method_exists($paymentResponse, 'getTransactionId')
            && method_exists($paymentResponse, 'getTransferId')
        ) {
            MerchantTransactionWebhook::create([
                'id_task'             => (int)$paymentResponse->getTransactionId(),
                'merchant_service_id' => (string)$paymentResponse->getTransferId(),
                'id_currency'         => (int)$transaction->getCurrencyIn()->id,
                'id_merchant'         => (int)$merchant->id,
                'provider'            => (string)$merchant->alias,
                'json_callbacks'      => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'amount'              => $paidAmount,
            ]);
        }

        // финальный статус по сумме (твои правила)
        $finalStatus = $this->resolveFinalTaskStatusByAmount(
            task: $task,
            merchant: $merchant,
            transaction: $transaction,
            paidAmount: $paidAmount,
            ip: $ip,
            userAgent: $userAgent,
            payload: $payload,
        );

        $this->flowLogger->info(
            event: 'callback_amount_check_finished',
            task: $task,
            merchant: $merchant,
            ctx: [
                'ip' => $ip,
                'user_agent' => $userAgent,
                'context' => [
                    'paid' => $paidAmount,
                    'expected' => $this->expectedAmountForMerchant($task, $merchant),
                    'final_status' => $finalStatus,
                ],
            ],
            message: 'Проверка суммы выполнена. Выбран итоговый статус заявки.',
            stage: 'amount_check',
            flow: 'callback'
        );
        $transaction->setStatus($finalStatus);
        $task->update(['started_at' => Carbon::now()->toDateTimeString()]);

        // уведомления (как у тебя)
        if ((int) iEXSetting('is_mail_notify_order_manager') === 1) {
            dispatch(new AdminNewOrderJob($task))
                ->delay(now()->addSeconds(30))
                ->onQueue('low');
        }
        \iEXApp::telegramNotificationForChannel('new_order_for_operator', $task);

        $this->flowLogger->info(
            event: 'callback_payment_success',
            task: $task,
            merchant: $merchant,
            ctx: [
                'ip' => $ip,
                'user_agent' => $userAgent,
                'context' => [
                    'paid' => $paidAmount,
                    'final_status' => $finalStatus,
                    'payload' => $this->payloadPreview($payload),
                ],
            ],
            message: 'Оплата подтверждена. Заявка обновлена.',
            stage: 'final',
            flow: 'callback'
        );

        return new CallbackHttpResult(200, 'OK');
    }

    /**
     * Вычисляет итоговый статус заявки на основе полученной суммы.
     *
     * Важно: здесь же фиксируем ключевые ветки в merchant_flow_events,
     * чтобы было видно, почему заявка ушла в тот или иной статус.
     */
    private function resolveFinalTaskStatusByAmount(
        Task $task,
        GatewayMerchant $merchant,
        $transaction,
        float $paidAmount,
        string $ip,
        string $userAgent,
        array $payload,
    ): int {
        $statusInvalidMin = (int)($merchant->status_invalid_min_amount ?? 0);
        $statusInvalidMax = (int)($merchant->status_invalid_max_amount ?? 7);

        // При необходимости пересчитываем заявку по актуальному курсу, чтобы далее сравнение сумм
        // выполнялось с корректным «ожидаемым» значением.
        if ((int) iEXSetting('is_recount_to_merchant') === 1) {
            $expiredAt = Carbon::parse($task->created_at)->addSeconds((int) iEXSetting('max_time_task'));
            $alreadyRecounted = (int)($task->task_info?->is_recounted_by_merchant ?? 0) === 1;

            if (!$alreadyRecounted && Carbon::now()->greaterThan($expiredAt)) {
                // Фиксируем значения ДО пересчёта
                $oldGivePriceWithComm = (string) $task->give_price_with_comm;

                $oldReceivingDefault = (string) ($task->receiving_price_default ?? '');
                $oldReceivingWithComm = (string) ($task->receiving_price_with_comm ?? '');
                $oldReceivingWithCommPay = (string) ($task->receiving_price_with_comm_pay ?? '');
                $oldReceivingReserve = (string) ($task->receiving_price_reserve ?? '');

                // Пересчёт должен выполняться от фактически поступившей суммы, потому что именно от неё
                // пересчитывается «Получаю» и связанные суммы.
                $amountForRecount = $paidAmount > 0
                    ? $paidAmount
                    : (float) ($task->in_amount_merchant ?? 0);

                // Фолбэк на сумму заявки, если по какой-то причине не удалось определить поступление
                if ($amountForRecount <= 0) {
                    $amountForRecount = (float) $task->give_price_with_comm;
                }

                // Важно: recount может изменить суммы, участвующие в expectedAmountForMerchant()
                $transaction->recount(1, $amountForRecount);

                // Обновляем модель один раз после пересчёта
                $freshTask = $task->fresh();

                $task->task_info?->update([
                    'is_recounted_by_merchant' => 1,
                ]);

                // уведомление
                if ((int) iEXSetting('is_recount_to_merchant_notify') === 1) {
                    $transaction->notifyRecountForMail();
                }

                $this->flowLogger->warning(
                    event: 'callback_recount_applied',
                    task: $task,
                    merchant: $merchant,
                    ctx: [
                        'ip' => $ip,
                        'user_agent' => $userAgent,
                        'context' => [
                            'expired_at' => $expiredAt->toIso8601String(),
                            'recount_amount' => $amountForRecount,
                            'paid_amount' => $paidAmount,
                            'old_give_price_with_comm' => $oldGivePriceWithComm,
                            'new_give_price_with_comm' => (string) $freshTask->give_price_with_comm,

                            // «Получаю» — ключевая часть при пересчёте от поступившей суммы
                            'old_receiving_price_default' => $oldReceivingDefault,
                            'new_receiving_price_default' => (string) ($freshTask->receiving_price_default ?? ''),
                            'old_receiving_price_with_comm' => $oldReceivingWithComm,
                            'new_receiving_price_with_comm' => (string) ($freshTask->receiving_price_with_comm ?? ''),
                            'old_receiving_price_with_comm_pay' => $oldReceivingWithCommPay,
                            'new_receiving_price_with_comm_pay' => (string) ($freshTask->receiving_price_with_comm_pay ?? ''),
                            'old_receiving_price_reserve' => $oldReceivingReserve,
                            'new_receiving_price_reserve' => (string) ($freshTask->receiving_price_reserve ?? ''),
                        ],
                    ],
                    message: 'Оплата пришла позже установленного времени — заявка пересчитана по актуальному курсу.',
                    stage: 'recount',
                    flow: 'callback'
                );

                $task = $freshTask;
            }
        }

        $expected = $this->expectedAmountForMerchant($task, $merchant);

        // успех по умолчанию
        $finalStatus = 7;

        // 1) Переплата
        if ($paidAmount > $expected) {
            $task->update(['merchant_overpayment' => 1]);
            $finalStatus = $statusInvalidMax;

            $this->flowLogger->warning(
                event: 'callback_amount_overpaid',
                task: $task,
                merchant: $merchant,
                ctx: [
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                    'context' => [
                        'paid' => $paidAmount,
                        'expected' => $expected,
                        'chosen_status' => $finalStatus,
                        'payload' => $this->payloadPreview($payload),
                    ],
                ],
                message: 'Оплата больше ожидаемой суммы. Заявка помечена как переплата.',
                stage: 'amount_check',
                flow: 'callback'
            );
        }

        // 2) Недоплата (с учётом погрешности)
        $faultAmount = (float)$transaction->getMerchantAmountForFault((string)($merchant->amount_fault ?? '0'), $expected);

        if ($paidAmount < $faultAmount) {
            $task->update([
                'is_bot' => 0,
                'merchant_incomplete_payment' => 1,
                'merchant_overpayment' => 2,
            ]);

            // Если политика = 0, то сразу отклоняем.
            if ($statusInvalidMin === 0) {
                $this->flowLogger->security(
                    event: 'callback_amount_underpaid_rejected',
                    task: $task,
                    merchant: $merchant,
                    ctx: [
                        'ip' => $ip,
                        'user_agent' => $userAgent,
                        'context' => [
                            'paid' => $paidAmount,
                            'fault_min' => $faultAmount,
                            'expected' => $expected,
                            'policy_status_invalid_min_amount' => $statusInvalidMin,
                            'payload' => $this->payloadPreview($payload),
                        ],
                    ],
                    message: 'Оплата меньше минимально допустимой суммы. Заявка отклонена по правилам мерчанта.',
                    stage: 'amount_check',
                    flow: 'callback'
                );

                $transaction->setCategoryReject(4)->reject();
                return (int)$task->status;
            }

            // Иначе — переводим в настроенный статус обработки недоплаты.
            if (in_array($statusInvalidMin, [3, 7, 12], true)) {
                $finalStatus = $statusInvalidMin;
            }

            $this->flowLogger->warning(
                event: 'callback_amount_underpaid',
                task: $task,
                merchant: $merchant,
                ctx: [
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                    'context' => [
                        'paid' => $paidAmount,
                        'fault_min' => $faultAmount,
                        'expected' => $expected,
                        'chosen_status' => $finalStatus,
                        'policy_status_invalid_min_amount' => $statusInvalidMin,
                        'payload' => $this->payloadPreview($payload),
                    ],
                ],
                message: 'Оплата меньше минимально допустимой суммы. Заявка помечена как недоплата.',
                stage: 'amount_check',
                flow: 'callback'
            );
        }


        // 3) Нормальная оплата (или переплата уже учтена выше)
        if ($paidAmount >= $faultAmount && $paidAmount <= $expected) {
            $this->flowLogger->info(
                event: 'callback_amount_ok',
                task: $task,
                merchant: $merchant,
                ctx: [
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                    'context' => [
                        'paid' => $paidAmount,
                        'fault_min' => $faultAmount,
                        'expected' => $expected,
                        'chosen_status' => $finalStatus,
                    ],
                ],
                message: 'Оплата в допустимых пределах. Продолжаем обработку заявки.',
                stage: 'amount_check',
                flow: 'callback'
            );
        }

        return $finalStatus;
    }

    /**
     * Ожидаемая сумма оплаты по правилам мерчанта.
     *
     * credit_amount:
     * 0 — give_price
     * 1 — give_price_with_comm_pay
     * 2 — give_price_default
     */
    private function expectedAmountForMerchant(Task $task, GatewayMerchant $merchant): float
    {
        return (float) match ((int)($merchant->credit_amount ?? 0)) {
            1 => (float) $task->give_price_with_comm_pay,
            2 => (float) $task->give_price_default,
            default => (float) $task->give_price,
        };
    }
}
