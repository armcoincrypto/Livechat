<?php

namespace iEXPackages\Transaction\Concerns;

use App\Enums\OrderPaymentStatus;
use App\Models\GatewayMerchant;
use App\Models\MerchantTransactionData;
use App\Models\MerchantTransactionHash;
use App\Models\TaskCheckPaymentStatusLog;
use App\Models\TaskMeta;
use App\Models\WalletTransaction;
use App\Support\Facades\iEXApp;
use Exception;
use iEXPackages\Payments\Core\Contracts\BlockchainPaymentResponseInterface;
use iEXPackages\Payments\Core\Contracts\FetchPaymentResponseInterface;
use iEXPackages\Payments\Payments;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Автоматическая проверка оплаты
 */
trait SupportsCheckPayment
{
    /**
     * Дополнительно проверяем полученную сумму
     */
    protected bool $accurate_balance = false;

    /**
     * Получаем сумму с учетом погрешности.
     *
     * @param string $amount_fault Допустимая погрешность (в процентах или абсолютная величина)
     * @param float $expectedAmount Ожидаемая сумма
     * @return float
     * @throws InvalidArgumentException
     */
    public function getMerchantAmountForFault(string $amount_fault, float $expectedAmount): float
    {
        if (empty($amount_fault)) {
            return $expectedAmount;
        }

        if (Str::endsWith($amount_fault, '%')) {
            $faultPercentage = (float)Str::remove('%', $amount_fault);

            if ($faultPercentage < 0 || $faultPercentage > 100) {
                throw new InvalidArgumentException('Недопустимое значение процентной погрешности');
            }

            $faultAmount = $expectedAmount * ($faultPercentage / 100);
        } else {
            $faultAmount = (float)$amount_fault;

            if ($faultAmount < 0 || $faultAmount > $expectedAmount) {
                throw new InvalidArgumentException('Недопустимое абсолютное значение погрешности');
            }
        }

        $newAmount = $expectedAmount - $faultAmount;

        return round(max($newAmount, 0), 8); // округляем до 8 знаков после запятой
    }


    public function hasTimeOutCheckPayment(): bool
    {
        $merchant_pay = $this->getMerchant();
        $max_time_register_in_network = (int)($merchant_pay->ext_options['max_time_register_in_network'] ?? 0);
        $max_time_confirm_in_network = (int)($merchant_pay->ext_options['max_time_confirm_in_network'] ?? 0);
        $now = Carbon::now();

        if ($max_time_register_in_network > 0 and $max_time_confirm_in_network > 0) {
            if ($this->transaction->register_tx == 0) {
                $registerDeadlinePassed = Carbon::parse($this->transaction->created_at)
                    ->addMinutes($max_time_register_in_network) < $now;

                if ($registerDeadlinePassed) {
                    // USDT TRC20 (Tron): не авто-отклоняем из‑за задержки polling / индексации.
                    // Возвращаем false, чтобы merchant:webhook не переводил заявку в «таймаут» (is_bot=0).
                    if ($this->incomingPaymentIsUsdtTrc20()) {
                        return false;
                    }

                    $this->transaction->update(['is_bot' => 0]);
                    $this->reject();
                }

                return $registerDeadlinePassed;
            }

            // В ожидании 1-го подтверждения от сети
            return Carbon::parse($this->transaction->created_at)->addHours($max_time_confirm_in_network) < $now;
        }

        return false;
    }

    /**
     * Входящая валюта заявки — USDT в сети Tron (TRC20).
     */
    private function incomingPaymentIsUsdtTrc20(): bool
    {
        try {
            $currency = $this->getCurrencyIn();
        } catch (\Throwable) {
            return false;
        }

        $currency->loadMissing('code_currency');

        $base = strtoupper((string) ($currency->code_currency?->name ?? ''));
        if ($base !== 'USDT') {
            return false;
        }

        $designation = strtoupper((string) ($currency->designation_xml ?? ''));
        $networkCode = strtoupper((string) ($currency->network_code ?? ''));
        $tech = strtoupper((string) ($currency->tech_currency_name ?? ''));

        return str_contains($designation, 'TRC20')
            || str_contains($networkCode, 'TRC20')
            || str_contains($tech, 'TRC20')
            || $networkCode === 'TRX';
    }

    protected function handleMerchantDisabled(): array
    {
        $message = sprintf(
            'Проверка платежей отключена или не настроена для данного мерчанта (задача #%s).',
            $this->transaction->id
        );

        Log::warning($message, ['task_id' => $this->transaction->id]);

        return ['status' => 1, 'message' => $message];
    }

    protected function validateAMLAddressIfRequired(): void
    {
        if ($this->getCurrencyOut()->id_aml_service > 0 && $this->getCurrencyOut()->is_aml_check_wallet == 2) {
            $this->validatedAMLAddress($this->getCurrencyOut());
        }
    }

    protected function handleCancelledPayment($checkPayment, TaskMeta $meta): array
    {
        $reason = $checkPayment->getStatusDescription();

        // Подробно логируем причину отмены
        Log::warning('Транзакция отменена провайдером платежей', [
            'task_id' => $this->transaction->id,
            'reason' => $checkPayment->getStatusDescription(),
        ]);

        $this->logPaymentStatusChange($meta, OrderPaymentStatus::TRANSACTION_FAILED, "Платеж отменен, причина: $reason");

        $this->reject();

        return [
            'status' => 1,
            'message' => "Транзакция отменена: $reason"
        ];
    }

    /**
     * Выполняет AML-проверку транзакции.
     *
     * @param string $transactionID ID транзакции в блокчейне
     * @param MerchantTransactionData $merchantTxData Данные транзакции мерчанта
     * @param TaskMeta $meta Метаданные текущей задачи
     *
     * @throws Exception
     */
    protected function performAMLCheck(string $transactionID, MerchantTransactionData $merchantTxData, TaskMeta $meta): void
    {
        $account = preg_replace('/\s+/', '', $merchantTxData->ext_data['wallet_number'] ?? '');

        if ($this->getCurrencyIn()->id_aml_service > 0 && $this->getCurrencyIn()->is_aml_check_tx === 1) {
            $this->logPaymentStatusChange($meta, OrderPaymentStatus::AML_CHECK_IN_PROGRESS, 'Проводится AML-проверка транзакции');

            try {
                $this->validatedInTransaction($this->getCurrencyIn(), [
                    'currency' => $this->getCurrencyIn()->designation_xml,
                    'address' => $account,
                    'client_id' => $this->transaction->id_user,
                    'tx' => $transactionID,
                ]);
            } catch (\Exception $exception) {
                Log::error('Ошибка AML-проверки транзакции', [
                    'exception' => $exception->getMessage(),
                    'currency' => $this->getCurrencyIn()->designation_xml,
                    'address' => $account,
                    'transactionID' => $transactionID,
                    'task_id' => $this->transaction->id,
                ]);

                $this->logPaymentStatusChange(
                    $meta,
                    OrderPaymentStatus::AML_KYC_REQUIRED,
                    'Транзакция требует прохождения KYC согласно AML-политике'
                );

                throw $exception;
            }
        }
    }

    /**
     * Проверить поступление средств
     *
     * @throws Exception
     */
    public function checkInPayment(array $params = []): array
    {
        try {

            $merchantPay = $this->getMerchant();

            if (!$merchantPay) {
                return $this->handleMerchantDisabled();
            }

            // meta + статус "ждём"
            $meta = $this->transaction->meta()->firstOrCreate();
            $this->logPaymentStatusChange($meta, OrderPaymentStatus::WAITING_TRANSACTION, 'Ожидаем поступления транзакции');

            $this->validateAMLAddressIfRequired();

            // Runtime gateway
            $gateway = Payments::forMerchant($merchantPay);

            $merchantTxData = MerchantTransactionData::firstWhere('id_task', $this->transaction->id);
            $externalId = (string)(optional($merchantTxData)->id_from_merchant ?? '');

            if ($externalId === '') {
                return [
                    'status' => 1,
                    'message' => 'ID платежа у провайдера отсутствует',
                ];
            }


            // Запрос к провайдеру
            try {

                $paymentResponse = $gateway->fetchPayment([
                    'transactionId' => (string) $this->transaction->id,
                    'externalId' => $externalId,
                    'minOrderAmount' => $this->getOrderMinAmount(),
                    'currency' => $this->getCodeIn()->name ?? ''
                ])->withTask($this->transaction)->send();

            } catch (\Throwable $e) {
                return [
                    'status' => 1,
                    'message' => 'Ошибка запроса к провайдеру: ' . $e->getMessage(),
                ];
            }

            // 1) exists()
            if (!($paymentResponse instanceof FetchPaymentResponseInterface) || !$paymentResponse->exists()) {
                return [
                    'status' => 1,
                    'message' => 'Данные не получены',
                ];
            }

            // 2) cancelled
            if ($paymentResponse->isCancelled()) {
                return $this->handleCancelledPayment($paymentResponse, $meta);
            }

            // 4) Blockchain: hash/register/confirmations/AML
            $transactionHash = '';
            if ($paymentResponse instanceof BlockchainPaymentResponseInterface) {
                $transactionHash = (string)($paymentResponse->getTransactionHash() ?? '');


                // register_tx (только если разрешено)
                if ($transactionHash !== '' && $paymentResponse->canRegisterTransaction()) {
                    DB::transaction(function () use ($merchantPay, $transactionHash, &$meta) {
                        $transaction = $this->transaction->newQuery()
                            ->lockForUpdate()
                            ->with(['task_info', 'direction_exchange'])
                            ->find($this->transaction->id);

                        if (!$transaction) {
                            return;
                        }

                        // 4.1) Включаем register_tx один раз
                        if ((int)$transaction->register_tx === 0) {
                            $transaction->update(['register_tx' => 1]);

                            $meta = $this->logPaymentStatusChange(
                                $meta,
                                OrderPaymentStatus::REGISTERING_TRANSACTION,
                                'Идет регистрация транзакции в сети'
                            );
                        }

                        // 4.2) Если включено register_tx — фиксируем hash
                        if ((int)$transaction->register_tx === 1 && $transactionHash !== '') {
                            $hashRow = MerchantTransactionHash::where('id_task', $transaction->id)->first();

                            if (!$hashRow) {
                                MerchantTransactionHash::create([
                                    'id_task' => $transaction->id,
                                    'provider' => (string)$merchantPay->alias,
                                    'id_currency' => (int)($transaction->direction_exchange->id_currency1 ?? 0),
                                    'transaction_hash' => $transactionHash,
                                ]);

                                $meta = $this->logPaymentStatusChange(
                                    $meta,
                                    OrderPaymentStatus::TRANSACTION_REGISTERED,
                                    'Транзакция успешно зарегистрирована'
                                );
                            } else {
                                if ((string)($hashRow->transaction_hash ?? '') !== $transactionHash) {
                                    $hashRow->update([
                                        'provider' => (string)$merchantPay->alias,
                                        'id_currency' => (int)($transaction->direction_exchange->id_currency1 ?? 0),
                                        'transaction_hash' => $transactionHash,
                                    ]);
                                }
                            }

                            if ($transaction->task_info && empty($transaction->task_info->id_transaction_merchant)) {
                                $transaction->task_info->update(['id_transaction_merchant' => $transactionHash]);
                            }
                        }
                    });

                    $this->transaction->refresh();
                }

                // 4.3) Confirmations (если шлюз поддерживает)
                $min_confirm = (int)($merchantPay->ext_options['min_confirm'] ?? 0);


                $needsMore = null;
                if (method_exists($paymentResponse, 'needsMoreConfirmations')) {
                    $needsMore = (bool)$paymentResponse->needsMoreConfirmations();
                }

                if ($needsMore === true) {
                    $this->logPaymentStatusChange(
                        $meta,
                        OrderPaymentStatus::TRANSACTION_PENDING_CONFIRMATION,
                        'Транзакция найдена, ожидается подтверждение от сети'
                    );

                    $confirm = ['current' => 0, 'required' => 0];

                    if (method_exists($paymentResponse, 'getTransactionConfirmations')) {
                        $tmp = $paymentResponse->getTransactionConfirmations();
                        if (is_array($tmp)) {
                            $confirm['current'] = (int)($tmp['current'] ?? 0);
                            $confirm['required'] = (int)($tmp['required'] ?? 0);
                        }
                    }

                    return [
                        'status' => 1,
                        'event' => 'not_min_confirm',
                        'message' => sprintf(
                            'Недостаточно подтверждений. Получено %d из %d.',
                            $confirm['current'],
                            $confirm['required']
                        ),
                    ];
                }


                // Legacy confirm check (если шлюз даёт только текущие подтверждения)
                if ($min_confirm > 0 && method_exists($paymentResponse, 'getConfirmationsCurrent')) {
                    $confirmations = (int)$paymentResponse->getConfirmationsCurrent();
                    if ($confirmations < $min_confirm) {
                        $this->logPaymentStatusChange(
                            $meta,
                            OrderPaymentStatus::TRANSACTION_PENDING_CONFIRMATION,
                            'Транзакция найдена, ожидается подтверждение от сети'
                        );

                        return [
                            'status' => 1,
                            'event' => 'not_min_confirm',
                            'message' => sprintf(
                                'Недостаточно подтверждений. Получено %d из %d.',
                                $confirmations,
                                $min_confirm
                            ),
                        ];
                    }
                }


                // 4.4) AML
                if ($transactionHash !== '') {
                    try {
                        $this->performAMLCheck($transactionHash, $merchantTxData, $meta);
                    } catch (\Exception $e) {
                        return [
                            'status' => 1,
                            'message' => sprintf('Ошибка AML-проверки: %s', $e->getMessage()),
                        ];
                    }
                }
            }

            // 3) Фиксируем сумму (один раз)
            if (empty($this->transaction->in_amount_merchant) && method_exists($paymentResponse, 'getAmount')) {
                $amount = $paymentResponse->getAmount();
                if ($amount !== null && (float)$amount > 0) {
                    $this->transaction->update([
                        'in_amount_merchant' => (string)$amount,
                    ]);
                    $this->transaction->refresh();
                }
            }


            // 5) Pending
            if ($paymentResponse->isPending()) {
                $this->logPaymentStatusChange($meta, OrderPaymentStatus::TRANSACTION_FOUND, 'Транзакция найдена, идет обработка');

                $desc = method_exists($paymentResponse, 'getStatusDescription')
                    ? (string)($paymentResponse->getStatusDescription() ?? 'Транзакция в обработке')
                    : 'Транзакция в обработке';

                return [
                    'status' => 1,
                    'message' => $desc,
                ];
            }


            // 6) Successful -> вся твоя бизнес-логика
            if ($paymentResponse->isSuccessful()) {

                $this->logPaymentStatusChange($meta, OrderPaymentStatus::CONFIRMATIONS_DONE, 'Все подтверждения получены, ожидается завершение');

                $statusInvalidMinAmount = $merchantPay->status_invalid_min_amount ?? 0;
                $statusInvalidMaxAmount = $merchantPay->status_invalid_max_amount ?? 7;

                $changeStatus = 7;

                $receivedAmount = method_exists($paymentResponse, 'getAmount')
                    ? (float)($paymentResponse->getAmount() ?? 0)
                    : (float)($this->transaction->in_amount_merchant ?? 0);

                $expectedAmount = $this->transaction->give_price;
                if ($merchantPay->credit_amount == 1) {
                    $expectedAmount = $this->transaction->give_price_with_comm_pay;
                } elseif ($merchantPay->credit_amount == 2) {
                    $expectedAmount = $this->transaction->give_price_default;
                }

                try {
                    $allowedAmount = $this->getMerchantAmountForFault($merchantPay->amount_fault, $expectedAmount);
                } catch (\InvalidArgumentException $e) {
                    return [
                        'status' => 1,
                        'message' => 'Ошибка расчета погрешности: ' . $e->getMessage(),
                    ];
                }

                // Недоплата
                if ($receivedAmount < $allowedAmount) {

                    if ((int)$statusInvalidMinAmount === 0) {
                        $this->logPaymentStatusChange(
                            $meta,
                            OrderPaymentStatus::TRANSACTION_BLOCKED_INSUFFICIENT_AMOUNT,
                            sprintf(
                                'Транзакция заблокирована: поступило меньше средств (получено: %s, ожидалось минимум: %s)',
                                $receivedAmount,
                                $allowedAmount
                            )
                        );

                        $this->setCategoryReject(4)->reject();

                        return [
                            'status' => 1,
                            'message' => sprintf(
                                'Транзакция заблокирована: отправленная сумма меньше минимально допустимой. Получено: %s, ожидалось минимум: %s',
                                $receivedAmount,
                                $allowedAmount
                            ),
                        ];
                    }

                    if ((int)$statusInvalidMinAmount === 1 && $this->getCheckAccurateBalance()) {
                        if ($receivedAmount < $this->getOrderMinAmount()) {
                            $this->logPaymentStatusChange(
                                $meta,
                                OrderPaymentStatus::TRANSACTION_BLOCKED_INSUFFICIENT_AMOUNT,
                                'Транзакция заблокирована, отправлена меньшая сумма, чем указано в заявке'
                            );

                            $this->inFlowFunds();
                            $this->autoBanClientForAutoPayment();

                            return [
                                'status' => 1,
                                'message' => sprintf(
                                    'Ошибка: сумма поступления меньше минимальной допустимой (%s). Получено: %s.',
                                    $this->getOrderMinAmount(),
                                    $receivedAmount
                                ),
                            ];
                        }

                        $this->recount(0, $receivedAmount);
                    }

                    if (in_array((int)$statusInvalidMinAmount, [3, 7, 12], true)) {
                        $changeStatus = (int)$statusInvalidMinAmount;
                    }
                }

                // Переплата
                if ($receivedAmount > $expectedAmount) {
                    $changeStatus = (int)$statusInvalidMaxAmount;
                }

                // Валюта (если шлюз возвращает)
                $currencyName = null;
                if (method_exists($paymentResponse, 'getCurrency')) {
                    $currencyName = $paymentResponse->getCurrency();
                } elseif (method_exists($paymentResponse, 'getCurrencyWithNetwork')) {
                    $currencyName = $paymentResponse->getCurrencyWithNetwork();
                }

                $currencyCode = strtoupper((string)$this->getCodeIn()->name);

                if (!empty($currencyName) && $currencyName !== $currencyCode) {
                    $this->logPaymentStatusChange(
                        $meta,
                        OrderPaymentStatus::TRANSACTION_BLOCKED_WRONG_CURRENCY,
                        'Транзакция заблокирована: поступили средства в неправильной валюте'
                    );

                    return [
                        'status' => 1,
                        'message' => sprintf('Оплачено в другой валюте (%s), ожидалась (%s).', $currencyName, $currencyCode),
                    ];
                }

                // WalletTransaction/confirmation history (если есть hash/confirmations)
                if ($transactionHash !== '' || (method_exists($paymentResponse, 'getTransactionHash') && $paymentResponse->getTransactionHash())) {
                    $txid = $transactionHash !== '' ? $transactionHash : (string)($paymentResponse->getTransactionHash() ?? '');

                    $data = ['id_task' => $this->transaction->id];

                    $values = [
                        'amount' => $receivedAmount,
                        'confirmations' => method_exists($paymentResponse, 'getTransactionConfirmation')
                            ? (int)$paymentResponse->getTransactionConfirmation()
                            : 1,
                    ];

                    if ($txid !== '') {
                        $values['txid'] = $txid;
                    }

                    $wallet_tx = WalletTransaction::updateOrCreate($data, $values);

//                    if (method_exists($paymentResponse, 'getTransactionConfirmation')) {
//                        TaskLogConfirmation::create([
//                            'id_task' => $this->transaction->id,
//                            'confirmation' => (int)$paymentResponse->getTransactionConfirmation(),
//                        ]);
//                    }

                    if ($wallet_tx->wasRecentlyCreated) {
                        $this->writePaymentTxHistory($values, $merchantPay->alias);
                    }
                }

                if (in_array($this->getStatus(), [3, 12, 13], true)) {
                    $this->setStatus($changeStatus);
                }

                $this->logPaymentStatusChange($meta, OrderPaymentStatus::PAYMENT_SUCCESS, 'Оплата успешно подтверждена');

                return [
                    'status' => 0,
                    'message' => 'Все успешно',
                ];
            }

            return [
                'status' => 1,
                'message' => 'Платеж в обработке',
            ];
        } catch (\Throwable $e) {
            Log::error('Ошибка проверки платежа', [
                'exception' => $e->getMessage(),
                'task_id' => $this->transaction->id,
            ]);

            return [
                'status' => 1,
                'message' => $e->getMessage(),
            ];
        }
    }


    /**
     * Логирует изменение статуса платежа с защитой от race conditions.
     *
     * @param TaskMeta $meta Мета-данные задачи
     * @param OrderPaymentStatus $newStatus Новый статус оплаты
     * @param string|null $description Описание изменения статуса
     *
     * @return TaskMeta Актуальные мета-данные после изменения
     *
     * @throws \Throwable
     */
    private function logPaymentStatusChange(TaskMeta $meta, OrderPaymentStatus $newStatus, ?string $description = null): TaskMeta
    {
        DB::transaction(function () use ($meta, $newStatus, $description) {
            $lockedMeta = TaskMeta::lockForUpdate()->findOrFail($meta->id);

            if ($lockedMeta->payment_status === $newStatus) {
                return;
            }

            $lastLog = TaskCheckPaymentStatusLog::query()
                ->where('task_id', $lockedMeta->task_id)
                ->latest('id')
                ->first();

            if (!$lastLog || $lastLog->new_status !== $newStatus->value) {
                TaskCheckPaymentStatusLog::create([
                    'task_id' => $lockedMeta->task_id,
                    'old_status' => $lockedMeta->payment_status ?? 0,
                    'new_status' => $newStatus->value,
                    'description' => $description,
                ]);
            }

            $lockedMeta->update(['payment_status' => $newStatus]);
        });

        // Явно обновляем исходный объект после транзакции
        $meta->refresh();

        return $meta;
    }

    /**
     * Включение или отключение дополнительной проверки полученной суммы
     *
     * @return SupportsCheckPayment
     */
    public function setCheckAccurateBalance(bool $bool = false): static
    {
        $this->accurate_balance = $bool;

        return $this;
    }

    /**
     * Включение или отключение дополнительной проверки полученной суммы
     */
    public function getCheckAccurateBalance(): bool
    {
        return $this->accurate_balance;
    }

    /**
     * Записывает транзакцию и события в историю платежей
     *
     * @param array $detail Детали транзакции
     * @param string|null $provider Название провайдера
     * @return WalletTransaction
     */
    private function writePaymentTxHistory(array $detail = [], ?string $provider = null): WalletTransaction
    {
        $response = WalletTransaction::create([
            'id_task' => $this->transaction->id,
            'address' => Arr::get($detail, 'address'),
            'amount' => Arr::get($detail, 'amount'),
            'confirmations' => Arr::get($detail, 'confirmations'),
            'txid' => Arr::get($detail, 'txid'),
        ]);
//
//        // Формируем сообщение для логов
//        $event_value = sprintf(
//            'Транзакция зафиксирована в блокчейне<br>
//        Сумма: <span style="color: green;">%s</span><br>
//        Код валюты: <span style="color: green;">%s</span><br>
//        ID Транзакции: %s',
//            $response->amount,
//            $this->getCodeIn()->name,
//            $response->txid
//        );
//
//        iex_order_merchant_log($this->transaction->id, 1, 2, $event_value, $provider);

        // Уведомляем через Telegram
        iEXApp::telegramNotificationForChannel('first_confirm_blockchain', $this->transaction);

//        // Лог подтверждений, если они заданы
//        if (Arr::has($detail, 'confirmations')) {
//            TaskLogConfirmation::create([
//                'id_task' => $this->transaction->id,
//                'confirmation' => Arr::get($detail, 'confirmations'),
//            ]);
//        }

        return $response;
    }
}
