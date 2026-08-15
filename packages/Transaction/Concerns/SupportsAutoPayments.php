<?php
declare(strict_types=1);

namespace iEXPackages\Transaction\Concerns;

use App\Models\AutoSenderPayment;
use App\Models\MerchantTransactionData;
use App\Models\PayTransactionData;
use App\Models\Task;
use App\Models\WalletsHistory;
use App\Support\Facades\iEXApp;
use Carbon\Carbon;
use iEXPackages\Payments\Core\Contracts\PayoutTrackingResponseInterface;
use iEXPackages\Payments\Core\Contracts\PollingResponseInterface;
use iEXPackages\Payments\Payments;
use iEXPackages\Transaction\Payment;
use Illuminate\Support\Facades\Log;

trait SupportsAutoPayments
{
    private mixed $apiService;

    /**
     * Откуда идет выплата?
     *
     * @var string
    */
    private string $isTypePay = 'currency';


    /**
     * Авто-выплата через CRON
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function autoPaymentViaCron(): array
    {
        if ((int) ($this->transaction->task_info->is_freeze_scam ?? 0) === 1) {
            throw new \Exception('Выплата приостановлена: данные заявки в черном списке');
        }

        $taskId = (int) $this->transaction->id;

        $fail = function (string $stage, string $message, array $context = [], bool $disableBot = false, ?int $forceStatus = null): array {
            Log::warning('autopay_failed', [
                'task_id' => $this->transaction->id,
                'stage' => $stage,
                'message' => $message,
                'context' => $context,
            ]);

            if ($disableBot) {
                $this->disableIsBot();
            }

            if ($forceStatus !== null) {
                $this->setStatus($forceStatus);
            }

            return [
                'status' => 1,
                'stage' => $stage,
                'message' => $message,
                'context' => $context,
            ];
        };

        try {
            $result = $this->autoPaymentLoaded();

            if (!empty($result['is_disable_bot'])) {
                $this->disableIsBot();
            }

            if (($result['status'] ?? 1) === 1) {
                return $fail(
                    stage: (string) ($result['stage'] ?? 'autopay_loaded'),
                    message: (string) ($result['message'] ?? 'Неизвестная ошибка автовыплаты'),
                    context: (array) ($result['context'] ?? ['task_id' => $taskId]),
                    disableBot: (bool) ($result['is_disable_bot'] ?? false)
                );
            }

            // Отложенный успех (tracking/polling)
            if (!empty($result['defer_success'])) {
                // 15 = «Выплата в процессе» — ожидаем подтверждение от сервиса/трекинга
                $this->setStatus(15);

                Log::info('autopay_deferred', [
                    'task_id' => $taskId,
                    'context' => $result['context'] ?? [],
                ]);

                return [
                    'status' => 0,
                    'stage' => 'deferred',
                    'message' => 'Выплата инициирована. Ожидаем подтверждение от платёжного сервиса.',
                    'defer_success' => true,
                    'context' => (array) ($result['context'] ?? ['task_id' => $taskId]),
                ];
            }

            // Успех
            $this->success();

            Log::info('autopay_success', [
                'task_id' => $taskId,
                'context' => $result['context'] ?? [],
            ]);

            return [
                'status' => 0,
                'stage' => 'success',
                'message' => 'Автовыплата выполнена успешно.',
                'context' => (array) ($result['context'] ?? ['task_id' => $taskId]),
            ];
        } catch (\Throwable $exception) {
            Log::error('autopay_exception', [
                'task_id' => $taskId,
                'error' => $exception->getMessage(),
            ]);

            // При исключении переводим в ручной режим и помечаем ошибку авто-выплаты
            $this->disableIsBot();
            $this->setStatus(14);

            iEXApp::telegramNotificationForChannel(
                'failed_for_pay',
                $this->transaction,
                $exception->getMessage()
            );

            return [
                'status' => 1,
                'stage' => 'exception',
                'message' => $exception->getMessage(),
                'context' => ['task_id' => $taskId],
            ];
        }
    }

    /**
     * Получаем актуальную систему выплаты
     *
     * @return mixed
     * @throws \Exception
     */
    private function getPayService(): mixed
    {
        $directionExchange = $this->transaction->direction_exchange;
        $currency = $directionExchange->currency2;

        // Определяем источник оплаты (direction или currency)
        $source = collect([$directionExchange, $currency])
            ->first(fn($item) => $item->gateway_payments->where('status', 1)->isNotEmpty());

        if (!$source) {
            throw new \Exception('Не определена система авто-выплаты');
        }

        $this->isTypePay = ($source === $directionExchange) ? 'direction_exchange' : 'currency';

        // Получаем сервисы оплаты со статусом 1
        $availableServices = $source->gateway_payments->where('status', 1);

        // Фильтруем сервисы по лимитам
        $filteredServices = $availableServices->filter(fn($service) => $this->serviceIsWithinLimits($service, $source));

        if ($filteredServices->isEmpty()) {
            throw new \Exception('Нет доступных платежных сервисов после проверки лимитов');
        }

        // Выбираем случайный сервис из доступных
        $this->apiService = $filteredServices->random();

        return $this->apiService;
    }

    // Отдельный метод проверки лимитов для читаемости
    private function serviceIsWithinLimits($service, $source): bool
    {
        $today = [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()];
        $currentMonth = [Carbon::today()->startOfMonth(), Carbon::today()->endOfMonth()];

        $dayLimitAmount = Task::where('id_pay', $service->id)->whereBetween('updated_at', $today)->sum('receiving_price');
        $monthLimitAmount = Task::where('id_pay', $service->id)->whereBetween('updated_at', $currentMonth)->sum('receiving_price');

        $dayOrderCount = Task::where('id_pay', $service->id)->whereIn('status', [3, 4])->whereBetween('updated_at', $today)->count();
        $monthOrderCount = Task::where('id_pay', $service->id)->whereIn('status', [3, 4])->whereBetween('updated_at', $currentMonth)->count();

        $day_limit_amount_pay = $source->pay_day_limit_amount ?: $service->day_limit_amount_pay;
        $month_limit_amount_pay = $source->pay_month_limit_amount ?: $service->month_limit_amount_pay;
        $min_amount_for_per_order = $source->pay_min_amount_for_order ?: $service->min_amount_for_per_order;
        $max_amount_for_per_order = $source->pay_max_amount_for_order ?: $service->max_amount_for_per_order;
        $day_limit_pay = $source->pay_day_limit ?: $service->day_limit_pay;
        $month_limit_pay = $source->pay_month_limit ?: $service->month_limit_pay;

        // Проверяем превышение дневного лимита суммы
        if ($day_limit_amount_pay > 0 && $dayLimitAmount >= $day_limit_amount_pay) {
            return false;
        }

        // Проверяем превышение месячного лимита суммы
        if ($month_limit_amount_pay > 0 && $monthLimitAmount >= $month_limit_amount_pay) {
            return false;
        }

        // Проверяем минимальную и максимальную сумму на заявку
        if (($min_amount_for_per_order > 0 && $this->transaction->receiving_price < $min_amount_for_per_order) ||
            ($max_amount_for_per_order > 0 && $this->transaction->receiving_price > $max_amount_for_per_order)) {
            return false;
        }

        // Проверяем дневной и месячный лимит заявок
        if (($day_limit_pay > 0 && $dayOrderCount >= $day_limit_pay) ||
            ($month_limit_pay > 0 && $monthOrderCount >= $month_limit_pay)) {
            return false;
        }

        return true;
    }
    /**
     * Функция для выполнения авто-выплаты
     *
     * @throws \Exception
     */
    public function autoPaymentLoaded(): array
    {
        $this->apiService = [];
        $this->getPayService();
        $taskId = (int) $this->transaction->id;

        if (empty($this->apiService)) {
            throw new \Exception('Автовыплата недоступна');
        }

        // Функции для авто-выплаты через CRON
        if ($this->getIsCron())
        {
            // Если отключена авто-выплата, дальше не пускаем
            $allowAutopay = ($this->isTypePay === 'direction_exchange')
                ? $this->transaction->direction_exchange->allow_autopay
                : $this->transaction->direction_exchange->currency2->allow_autopay;

            $isAllowPay = match ($allowAutopay) {
                2 => true,  // Всегда разрешено
                1 => false, // Всегда запрещено
                default => (bool) $this->apiService->allow_autopay, // По умолчанию — смотрим у сервиса
            };

            if (!$isAllowPay) {
                throw new \Exception('Автовыплата отключена');
            }

            // Идемпотентность: если выплата уже инициирована — повтор запрещён.
            if (PayTransactionData::where('id_task', $taskId)->exists()) {
                return [
                    'status' => 1,
                    'stage' => 'idempotency',
                    'is_disable_bot' => true,
                    'message' => 'Выплата уже инициирована ранее. Повторный запуск запрещён.',
                    'context' => ['task_id' => $taskId],
                ];
            }

            // Доп. защита от параллельных/повторных запусков.
            if (AutoSenderPayment::where('id_order', $taskId)->exists()) {
                $this->turnOffAutoOutput();
                return [
                    'status' => 1,
                    'stage' => 'double_withdraw_guard',
                    'is_disable_bot' => true,
                    'message' => "Обнаружена повторная попытка авто-выплаты по заявке №{$taskId}. Авто-режим отключён.",
                    'context' => ['task_id' => $taskId],
                ];
            }
        }

        $merchantTxData = MerchantTransactionData::where('id_task', $this->transaction->id)->first();
        if (!$merchantTxData) {
            return [
                'status' => 1,
                'stage' => 'merchant_tx_data_missing',
                'is_disable_bot' => true,
                'message' => 'Нет данных MerchantTransactionData для авто-выплаты.',
                'context' => ['task_id' => $taskId],
            ];
        }

        // Проверка AML Адреса
        $account = preg_replace('/\s/', '', $merchantTxData->ext_data['wallet_number'] ?? '');
        if ($this->getCurrencyIn()->id_aml_service > 0 and $this->getCurrencyIn()->is_aml_check_tx == 2)
        {
            // Проверяем адрес мерчанта
            $transactionID = $this->getTaskInfo()->id_transaction_merchant;

            if (!empty($transactionID)) {
                try {
                    $this->validatedInTransaction($this->getCurrencyIn(), [
                        'currency' => $this->getCurrencyIn()->designation_xml,
                        'address' => $account,
                        'client_id' => $this->transaction->id_user,
                        'tx' => $transactionID,
                    ]);
                } catch (\Exception $exception) {
                    \Log::debug('Autopayment AML Check Error: ' . $exception->getMessage());
                    return [
                        'status' => 1,
                        'stage' => 'aml_check',
                        'message' => $exception->getMessage(),
                        'context' => ['task_id' => $taskId],
                    ];
                }
            }
        }

        $this->isConsoleCommission = true;

        // Запрещаем несколько раз отправлять заявки на выплату
        if (WalletsHistory::whereIdTask($taskId)->exists()) {
            return [
                'status' => 1,
                'stage' => 'wallets_history_guard',
                'is_disable_bot' => true,
                'message' => 'Ошибка автовыплаты: обнаружены записи WalletsHistory. Повторная отправка запрещена.',
                'context' => ['task_id' => $taskId],
            ];
        }

        $createPayment = new Payment($this->transaction, $this->apiService);

        $gatewayConnector = Payments::forPayment($this->apiService);

        // Идемпотентность уже проверили выше. Здесь выполняем выплату один раз.
        // Маркер попытки (защита от параллельных запусков) создаём непосредственно перед запросом.
        AutoSenderPayment::updateOrCreate([
            'id_order' => $taskId,
        ], [
            'comment' => "Маркер авто-выплаты (защита от повторного запуска) по заявке №{$taskId}",
        ]);

        $gatewayResponse = $gatewayConnector->payout([
            'amount' => $createPayment->getFinallyAmount(),
            'transactionId' => (string) $taskId,
            'currency' => $this->getCodeOut()->name ?? ''
        ])->withTask($this->transaction)->send();

        if ($gatewayResponse->isSuccessful())
        {
            $externalId = method_exists($gatewayResponse, 'getExternalId')
                ? (string) ($gatewayResponse->getExternalId() ?? '')
                : '';

            // Создаем запись для выплат
            $payData = PayTransactionData::create([
                'id_task'     => $this->transaction->id,
                'id_currency' => $this->getCurrencyOut()->id,
                'service_name'=> $this->apiService->alias,
                'id_pay'      => $this->apiService->id,
                'id_from_pay' => $externalId,
            ]);

            // Добавляем ID заявки выплаты, чтобы потом в консоли, проверять и вызвать запрос для получения транзакции
            if ($gatewayResponse instanceof PollingResponseInterface && $gatewayResponse->isPollingRequired())
            {
                $payData->update([
                    'ext_data' => array_merge((array) ($payData->ext_data ?? []), [
                        'polling_required'   => 1,
                        'polling_type'       => (string) ($gatewayResponse->getPollingType() ?? ''),
                        'polling_key'        => (string) ($gatewayResponse->getPollingKey() ?? ''),

                        'waiting_started_at' => Carbon::now()->toDateTimeString(),
                        'is_waiting_hash'    => 1,
                    ]),
                ]);
            }

            if ($gatewayResponse instanceof PayoutTrackingResponseInterface && $gatewayResponse->isTrackingRequired()) {

                $mode = $gatewayResponse->getTrackingMode();

                $payData->update([
                    'ext_data' => array_merge((array)($payData->ext_data ?? []), [
                        'tracking_required'   => 1,
                        'tracking_mode'       => $mode,
                        'tracking_key'        => (string)($gatewayResponse->getTrackingKey() ?? ''),
                        'defer_success'       => $gatewayResponse->isSuccessDeferred() ? 1 : 0,

                        'is_callback'         => $mode === 'cron' ? 1 : 0,
                    ]),
                ]);
            }

            $statusPayApi  = 0;
            $deferSuccess  = false;

            if ($gatewayResponse instanceof PayoutTrackingResponseInterface) {
                if ($gatewayResponse->isTrackingRequired()) {
                    $statusPayApi = 1;
                    $deferSuccess = $gatewayResponse->isSuccessDeferred();
                }
            } elseif ($gatewayResponse instanceof PollingResponseInterface) {
                if ($gatewayResponse->isPollingRequired()) {
                    $statusPayApi = 1;
                }
            }

            $this->transaction->update([
                'id_pay'         => $this->apiService->id,
                'status_pay_api' => $statusPayApi,
            ]);

            return [
                'status' => 0,
                'stage' => 'payout_sent',
                'defer_success' => $deferSuccess,
                'service_id' => (int) $this->apiService->id,
                'service_alias' => (string) ($this->apiService->alias ?? ''),
                'external_id' => $externalId,
                'context' => [
                    'task_id' => $taskId,
                    'service_id' => (int) $this->apiService->id,
                    'service_alias' => (string) ($this->apiService->alias ?? ''),
                    'external_id' => $externalId,
                    'defer_success' => (bool) $deferSuccess,
                ],
            ];
        }

        return [
            'status' => 1,
            'stage' => 'payout_failed',
            'is_disable_bot' => true,
            'message' => 'Платёжный сервис вернул ошибку при попытке выплаты.',
            'context' => [
                'task_id' => $taskId,
                'service_id' => (int) $this->apiService->id,
                'service_alias' => (string) ($this->apiService->alias ?? ''),
                'response' => (array) $gatewayResponse->toArray(),
            ],
        ];
    }
}
