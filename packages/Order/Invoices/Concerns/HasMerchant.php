<?php
declare(strict_types=1);

namespace iEXPackages\Order\Invoices\Concerns;

use App\Models\Currency;
use App\Models\GatewayMerchant;
use App\Models\MerchantTransactionData;
use App\Models\Task;
use App\Models\TaskField;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Carbon\Carbon;
use iEXPackages\Order\Invoices\Support\MerchantAccountValidator;
use iEXPackages\Payments\Core\Config\GatewayConfig;
use iEXPackages\Payments\Core\Contracts\RedirectResponseInterface;
use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use iEXPackages\Payments\Payments;
use iEXPackages\TagProcessors\TagProcessors;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Трейт HasMerchant.
 *
 * Управляет процессом выбора и назначения мерчантов, получения реквизитов и обработки ошибок.
 */
trait HasMerchant
{
    /**
     * Последний валидатор, применённый к реквизитам мерчанта.
     */
    protected ?string $lastMerchantValidatorType = null;

    /**
     * Результат последней проверки реквизитов мерчанта.
     */
    protected ?bool $lastMerchantValidatorPassed = null;

    /**
     * Получение реквизитов мерчанта с учётом настроек поведения при ошибке.
     *
     * @return array
     *
     * Пример использования:
     * $merchantAccount = $this->resolveMerchantAccountWithHandling();
     * if (empty($merchantAccount)) {
     *     throw new Exception('Не удалось получить реквизиты мерчанта');
     * }
     */
    protected function resolveMerchantAccountWithHandling(): array
    {
        if ($this->task->id_merchant > 0) {
            return $this->fetchMerchantTransactionData();
        }

        $setting = (int)iEXSetting('merchant_error_handling_strategy');

        Log::info('Получение мерчанта начато', [
            'task_id' => $this->task->id,
            'setting' => $setting,
        ]);

        return match ($setting) {
            0 => $this->resolveMerchantAccount(),
            1 => $this->resolveMerchantAccountWithRetries(),
            2 => $this->resolveMerchantAccountWithAlternative(),
            default => [],
        };
    }

    /**
     * Получение реквизитов мерчанта с повторными попытками.
     *
     * @param int $maxAttempts Максимальное количество попыток (по умолчанию 3).
     * @return array
     *
     * Пример использования:
     * $merchantAccount = $this->resolveMerchantAccountWithRetries(3);
     * if (empty($merchantAccount)) {
     *     Log::error('Получение мерчанта завершилось неудачей');
     * }
     */
    protected function resolveMerchantAccountWithRetries(int $maxAttempts = 3): array
    {
        if ($this->task->id_merchant > 0) {
            return $this->fetchMerchantTransactionData();
        }

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            Log::info("Попытка #{$attempt} получения мерчанта", ['task_id' => $this->task->id]);

            $merchantAccount = $this->resolveMerchantAccount();
            if (!empty($merchantAccount)) {
                Log::info("Успешно получили мерчант на попытке #{$attempt}", ['task_id' => $this->task->id]);
                return $merchantAccount;
            }

            Log::warning("Неудачная попытка #{$attempt}", ['task_id' => $this->task->id]);
        }

        Log::error('Все попытки получения мерчанта провалились', ['task_id' => $this->task->id]);
        return [];
    }

    /**
     * Получение реквизитов альтернативного мерчанта, если текущий недоступен.
     *
     * @return array
     *
     * Пример использования:
     * $merchantAccount = $this->resolveMerchantAccountWithAlternative();
     * if (empty($merchantAccount)) {
     *     Log::error('Альтернативный мерчант не найден');
     * }
     */
    protected function resolveMerchantAccountWithAlternative(): array
    {
        if ($this->task->id_merchant > 0) {
            return $this->fetchMerchantTransactionData();
        }

        Log::info('Попытка использовать альтернативный мерчант', ['task_id' => $this->task->id]);

        $alternativeMerchant = $this->task->direction_exchange->merchants
            ->where('status', 1)
            ->where('id', '!=', $this->task->id_merchant)
            ->sortBy([['priority', 'asc'], ['id', 'desc']])
            ->first(fn($merchant) => $this->passesMerchantLimits($merchant, $this->task->direction_exchange));

        if (!$alternativeMerchant) {
            Log::error('Альтернативный мерчант не найден', ['task_id' => $this->task->id]);
            return [];
        }

        $this->task->update(['id_merchant' => $alternativeMerchant->id]);
        $this->merchantPay = $alternativeMerchant;

        try {
            $this->getMerchantData();
        } catch (\Exception $e) {
            Log::error('Ошибка при получении данных альтернативного мерчанта', [
                'task_id' => $this->task->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }

        return $this->fetchMerchantTransactionData();
    }

    /**
     * Получение реквизитов мерчанта с базовыми проверками и назначением.
     *
     * @return array
     */
    protected function resolveMerchantAccount(): array
    {
        $merchantSource = $this->task->direction_exchange->merchants->where('status', 1)->isNotEmpty()
            ? $this->task->direction_exchange
            : $this->currencyIn;

        $merchantResponse = $this->assignMerchantToTask($merchantSource);

        if (empty($merchantResponse['id'])) {
            Log::error('Не найден подходящий мерчант', [
                'task_id' => $this->task->id,
                'source' => $merchantSource === $this->task->direction_exchange ? 'direction' : 'currency'
            ]);
            return [];
        }

        $this->task->update(['id_merchant' => $merchantResponse['id']]);

        try {
            $this->getMerchantData();
        } catch (\Exception $e) {
            Log::error("Ошибка получения данных мерчанта: {$e->getMessage()} - {$e->getFile()} - {$e->getLine()}", [
                'task_id' => $this->task->id,
                'merchant_id' => $merchantResponse['id']
            ]);
            return [];
        }

        $this->merchantPay = $this->task->merchant()->first();

        return $this->fetchMerchantTransactionData();
    }

    protected function fetchMerchantTransactionData(): array
    {
        $mtd = MerchantTransactionData::where('id_task', $this->task->id)->first();
        if (!$mtd) {
            return [];
        }

        // актуализируем $merchantPay при необходимости
        if (!$this->merchantPay) {
            $this->merchantPay = $this->task->merchant()->first();
        }

        $ext = is_array($mtd->ext_data) ? $mtd->ext_data : (array)$mtd->ext_data;

        // checkout (redirect / form)
        if ((int) ($mtd->is_checkout_url ?? 0) === 1) {
            $checkout = is_array($ext['checkout'] ?? null) ? $ext['checkout'] : [];

            $url        = trim((string) ($checkout['url'] ?? ''));
            $checkoutId = trim((string) ($checkout['id']  ?? ''));

            // Нормализуем URL: принимаем только https
            if ($url === '' || !preg_match('#^https://#i', $url)) {
                Log::error('MTD checkout без корректного https URL', [
                    'task_id'       => $this->task->id,
                    'mtd_id'        => $mtd->id,
                    'checkout_url'  => $url,
                    'ext_has_checkout' => isset($ext['checkout']),
                ]);
                return [];
            }

            $this->enableAutoCheckPaymentsIfNeeded();

            $flowMode = trim((string) (($ext['flow']['mode'] ?? '') ?: ''));

            $payload = [
                'checkout_url' => $url,
                'checkout_id'  => $checkoutId,
                'flow_mode'    => $flowMode,
            ];

            // отдаём только непустые строки
            return array_filter($payload, static fn($v) => $v !== '');
        }

        // адрес/кошелёк
        $addr = (string)($ext['wallet_number'] ?? '');
        if ($addr === '') {
            Log::error('MTD без wallet_number', ['task_id' => $this->task->id]);
            return [];
        }

        if (!$this->validateMerchantAccountOnce($addr, 'fetchMerchantTransactionData')) {
            return [];
        }

        $this->enableAutoCheckPaymentsIfNeeded();

        return array_filter([
            'account' => $addr,
            'tag'     => array_key_exists('memo_id', $ext) ? (string)$ext['memo_id'] : null,
            'label'   => array_key_exists('label', $ext) ? (string)$ext['label'] : null,
        ], static fn($v) => $v !== null && $v !== '');
    }

    /**
     * Включение автоматической проверки платежей по таймерам.
     *
     * @return void
     *
     * Пример использования:
     * $this->enableAutoCheckPaymentsIfNeeded();
     */
    protected function enableAutoCheckPaymentsIfNeeded(): void
    {
        if (!$this->merchantPay) {
            return;
        }

        $ext = is_array($this->merchantPay->ext_options)
            ? $this->merchantPay->ext_options
            : (array) $this->merchantPay->ext_options;

        $maxRegisterTime = (int)($ext['max_time_register_in_network'] ?? 0);
        $maxConfirmTime  = (int)($ext['max_time_confirm_in_network'] ?? 0);


        if ($maxRegisterTime > 0 && $maxConfirmTime > 0) {
            $paymentConfig = Payments::forConfig($this->merchantPay->alias);

            if ($paymentConfig->supportsPolling()) {
                $this->task->update([
                    'is_bot' => 1,
                    'is_auto_check_pay' => 1,
                ]);
            }
        }
    }

    /**
     * Получение и обработка данных мерчанта для платежа.
     *
     * @return array
     */
    protected function getMerchantData(): array
    {
        $cfg = Payments::forConfig($this->merchantPay->alias);

        // Только твой внутренний checkout-режим
        if ($this->isCheckoutMethod($cfg, (array)($this->merchantPay->ext_options ?? []))) {
            return $this->generateMerchantCheckout();
        }

        return $this->processMerchantPayment($cfg);
    }

    /**
     * Проверяет метод оплаты (checkout или стандартный).
     *
     * @param GatewayConfig $configProvider Конфигурация мерчанта.
     * @param array $extOptions Дополнительные опции мерчанта.
     * @return bool
     */
    protected function isCheckoutMethod(GatewayConfig $configProvider, array $extOptions): bool
    {

        if (array_key_exists('type_method_receiving_pay', $extOptions)) {
            return $extOptions['type_method_receiving_pay'] === 0;
        }

        return $configProvider->supportsCheckout();
    }

    /**
     * Генерирует URL для checkout-платежа.
     *
     * @return array
     */
    protected function generateMerchantCheckout(): array
    {
        if (!$this->currencyIn || !$this->merchantPay) {
            Log::error('Невозможно сгенерировать checkout: отсутствует валюта или мерчант', [
                'task_id' => $this->task->id,
            ]);
            return [];
        }

        $checkoutId  = sprintf('%s-%s', (string) $this->task->public_id, strtolower((string) Str::orderedUuid()));
        $checkoutUrl = rtrim((string) config('app.api_url'), '/') . '/payment_status/checkout/' . $checkoutId;

        MerchantTransactionData::updateOrCreate(
            ['id_task' => $this->task->id],
            [
                'id_currency'     => $this->currencyIn->id,
                'service_name'    => $this->merchantPay->alias,
                'id_merchant'     => $this->merchantPay->id,
                'is_checkout_url' => 1,
                'account_validator_type'   => null,
                'account_validator_passed' => null,
                'ext_data' => [
                    'flow' => [
                        'mode' => 'form',
                    ],
                    'checkout' => [
                        'id'  => $checkoutId,
                        'url' => $checkoutUrl,
                    ],
                ],
            ]
        );

        return [
            'checkout_url' => $checkoutUrl,
            'checkout_id'  => $checkoutId,
        ];
    }

    /**
     * Получение ссылок для уведомлений мерчанта (callback-urls).
     *
     * @param GatewayConfig $config
     * @return array
     *
     * Пример использования:
     * $urls = $this->getNoticeUrls($configProvider);
     */
    protected function getNoticeUrls(GatewayConfig $config): array
    {
        $frontend = rtrim((string) config('app.api_url'), '/');

        $urls = [
            'cancelUrl' => $frontend . '/payment_status/fail',
            'returnUrl' => $frontend . '/payment_status/success',
        ];

        // Новый callback-блок
        $callbackEnabled = $config->callbacksEnabled();
        $routeName       = $config->callbackRouteName();

        if ($callbackEnabled && !empty($routeName)) {
            $urls['notifyUrl'] = route(
                $routeName,
                [$this->merchantPay->alias, $this->merchantPay->security_hash]
            );
        }

        return $urls;
    }

    /**
     * Выполнение оплаты через мерчант.
     *
     * @param GatewayConfig $cfg Конфигурация мерчанта.
     * @return array
     *
     * Пример использования:
     * $paymentData = $this->processMerchantPayment($configProvider);
     * if (empty($paymentData)) {
     *     Log::error('Платеж через мерчант не выполнен');
     * }
     * @throws \Throwable
     */
    protected function processMerchantPayment(GatewayConfig $cfg): array
    {
        if (!$this->currencyIn || !$this->currencyIn->code_currency) {
            Log::error('Невозможно выполнить платёж: отсутствует валюта или её код', [
                'task_id'  => $this->task->id,
                'merchant' => $this->merchantPay->alias ?? null,
            ]);
            return [];
        }

        try {
            $gateway = Payments::forMerchant($this->merchantPay);

            $response = $gateway->purchase([
                'amount'        => $this->getFinallyAmount(),
                'currency'      => $this->currencyIn->code_currency->name,
                'transactionId' => (string) $this->task->id,
                ...$this->getNoticeUrls($cfg)
            ])->withTask($this->task)->send();

        } catch (\Throwable $e) {
            Log::error('Исключение при выполнении платежа через мерчант', [
                'task_id'  => $this->task->id,
                'merchant' => $this->merchantPay->alias ?? null,
                'error'    => $e->getMessage(),
            ]);
            return [];
        }


        if ($response instanceof RedirectResponseInterface && $response->isRedirect()) {
            $providerUrl = (string) ($response->getRedirectUrl() ?? '');
            $method      = strtoupper((string) $response->getRedirectMethod());
            $formData    = (array) $response->getRedirectData();

            if ($providerUrl === '' || !in_array($method, ['GET', 'POST'], true)) {
                Log::error('Merchant redirect response invalid', [
                    'task_id'  => $this->task->id,
                    'merchant' => $this->merchantPay->alias,
                    'url'      => $providerUrl,
                    'method'   => $method,
                ]);
                return [];
            }


            $externalId = method_exists($response, 'getExternalId') ? (string)($response->getExternalId() ?? '') : '';

            // твой единый checkout_id/checkout_url
            $checkoutId  = sprintf('%s-%s', (string) $this->task->public_id, strtolower((string) Str::orderedUuid()));
            $checkoutUrl = rtrim((string) config('app.api_url'), '/') . '/payment_status/checkout/' . $checkoutId;


            MerchantTransactionData::updateOrCreate(
                ['id_task' => $this->task->id],
                [
                    'id_currency'      => $this->currencyIn->id,
                    'service_name'     => $this->merchantPay->alias,
                    'id_merchant'      => $this->merchantPay->id,
                    'id_from_merchant' => $externalId !== '' ? $externalId : null,
                    'is_checkout_url'   => 1,
                    'ext_data' => [
                        'flow' => [
                            'mode' => $method === 'POST' ? 'form' : 'redirect',
                        ],

                        'checkout' => [
                            'id'  => $checkoutId,
                            'url' => $checkoutUrl,
                        ],

                        'redirect' => [
                            'provider_url' => $providerUrl,
                            'method'       => $method,
                            'data'         => $method === 'POST' ? $formData : [],
                        ],

                        'provider' => [
                            'external_id' => $externalId !== '' ? $externalId : null,
                            'payment_id'  => $externalId !== '' ? $externalId : null,
                        ],
                    ],
                ]
            );

            $task = Task::query()->whereKey($this->task->id)->first();

            if (!$task) {
                throw new \RuntimeException('Заявка не найдена.');
            }

            $task->forceFill([
                'merchant_provider'         => (string) $this->merchantPay->alias,
                'transfer_to_account'       => null,
                'transfer_to_account_type'  => 'merchant_' . (string) $this->merchantPay->alias,
                'is_check_payment_merchant' => (int) $cfg->supportsPollingIncoming(),
            ])->save();

            $this->task = $task;
            $this->enableAutoCheckPaymentsIfNeeded();
            $this->task->refresh();

            return [
                'checkout_url' => $checkoutUrl,
                'checkout_id'  => $checkoutId,
            ];
        }

        // 2) REQUISITES: обязаны быть успешны
        if (!$response->isSuccessful()) {
            $msg = method_exists($response, 'getErrorMessage') ? (string)$response->getErrorMessage() : 'unknown';
            Log::error('Merchant transaction failed', [
                'task_id'  => $this->task->id,
                'merchant' => $this->merchantPay->alias ?? null,
                'message'  => $msg,
            ]);
            return [];
        }

        // 3) Успешные реквизиты
        return $this->handleSuccessfulPayment($response, $cfg);
    }

    /**
     * Получение дополнительных полей для мерчанта.
     *
     * @return array
     *
     * Пример использования:
     * $additionalFields = $this->getSellAdditionalFields();
     */
    protected function getSellAdditionalFields(): array
    {
        return TaskField::where([
            ['id_task', $this->task->id],
            ['type_field', 'in'],
            ['alias', 'currency']
        ])->pluck('field_value', 'field_key')->toArray();
    }

    /**
     * Генерация описания для платежа мерчанта.
     *
     * @return string
     *
     * Пример использования:
     * $description = $this->getMerchantDescription();
     */
    protected function getMerchantDescription(): string
    {
        $comment = trim(is_string($this->merchantPay->comment) ? $this->merchantPay->comment : '');

        if ($comment !== '') {
            $processedComment = app(TagProcessors::class)
                ->setProcessor('order')
                ->setText($comment)
                ->setData($this->task)
                ->process()
                ->getText();

            return Str::markdown($processedComment);
        }

        return '#' . $this->task->id;
    }

    /**
     * Обработка успешного ответа после платежа.
     *
     * @param ResponseInterface $response Ответ от мерчанта.
     * @param GatewayConfig $configProvider Конфигурация мерчанта.
     * @return array
     *
     * Пример использования:
     * $successData = $this->handleSuccessfulPayment($response, $configProvider);
     * @throws \Throwable
     */
    protected function handleSuccessfulPayment(ResponseInterface $response, GatewayConfig $configProvider): array
    {
        if (!$this->task || !$this->task->id) {
            throw new \RuntimeException('Невозможно обработать успешный платёж: заявка не определена.');
        }

        if (!$this->merchantPay) {
            throw new \RuntimeException('Невозможно обработать успешный платёж: мерчант не определён.');
        }

        if (!$this->currencyIn) {
            throw new \RuntimeException('Невозможно обработать успешный платёж: валюта не определена.');
        }

        $accountNumber = method_exists($response, 'getAccountNumber')
            ? (string)($response->getAccountNumber() ?? '')
            : '';

        $accountTag = method_exists($response, 'getAccountTag')
            ? (string)($response->getAccountTag() ?? '')
            : '';

        $externalId = method_exists($response, 'getExternalId')
            ? (string)($response->getExternalId() ?? '')
            : '';

        $bankName = method_exists($response, 'getBankName')
            ? (string)($response->getBankName() ?? '')
            : null;

        if ($accountNumber === '') {
            Log::error('handleSuccessfulPayment: пустой accountNumber', [
                'task_id'  => $this->task->id,
                'merchant' => $this->merchantPay->alias,
            ]);

            return [];
        }

        $shouldIssueRequisites = $this->validateMerchantAccountOnce($accountNumber, 'handleSuccessfulPayment');

        DB::transaction(function () use ($accountNumber, $accountTag, $externalId, $bankName, $configProvider): void {
            $task = Task::query()
                ->whereKey($this->task->id)
                ->lockForUpdate()
                ->first();

            if (!$task) {
                throw new \RuntimeException('Заявка не найдена при обновлении данных мерчанта.');
            }

            MerchantTransactionData::updateOrCreate(
                ['id_task' => $task->id],
                [
                    'id_currency'      => $this->currencyIn->id,
                    'id_from_merchant' => $externalId !== '' ? $externalId : null,
                    'service_name'     => (string) $this->merchantPay->alias,
                    'id_merchant'      => (int) $this->merchantPay->id,
                    'account_validator_type'   => $this->lastMerchantValidatorType,
                    'account_validator_passed' => $this->lastMerchantValidatorPassed,
                    'is_checkout_url'  => 0,
                    'ext_data' => [
                        'wallet_number' => $accountNumber,
                        'memo_id'       => $accountTag !== '' ? $accountTag : null,
                        'bank_name'     => $bankName,
                        'label'         => $externalId !== '' ? $externalId : null,
                        'provider'      => [
                            'external_id' => $externalId !== '' ? $externalId : null,
                            // Kobbopay tracker_id / payment id — strongest provider unique id.
                            'payment_id'  => $externalId !== '' ? $externalId : null,
                        ],
                    ],
                ]
            );

            $task->forceFill([
                'merchant_provider'         => (string) $this->merchantPay->alias,
                'transfer_to_account'       => $accountNumber,
                'transfer_to_account_type'  => 'merchant_' . (string) $this->merchantPay->alias,
                'is_check_payment_merchant' => (int) $configProvider->supportsPollingIncoming(),
            ])->save();

            $this->task = $task;
        });

        $this->enableAutoCheckPaymentsIfNeeded();
        $this->task->refresh();


        if (!$shouldIssueRequisites) {
            return [];
        }

        return [
            'address' => $accountNumber,
            'tag'     => $accountTag,
            'label'   => $externalId,
        ];
    }

    /**
     * Назначение подходящего мерчанта задаче с проверкой лимитов.
     *
     * @param object $merchantData Данные для поиска мерчанта.
     * @return array
     *
     * Пример использования:
     * $merchant = $this->assignMerchantToTask($merchantData);
     */
    protected function assignMerchantToTask(object $merchantData): array
    {
        $merchant = $merchantData->merchants
            ->sortBy([['priority', 'asc'], ['id', 'desc']])
            ->first(fn($m) => $this->passesMerchantLimits($m, $merchantData));

        if (!$merchant) {
            Log::error('Нет подходящих мерчантов, удовлетворяющих лимитам.', [
                'task_id' => $this->task->id,
                'direction_exchange_id' => $merchantData->id ?? null,
                'checked_merchants' => $merchantData->merchants->pluck('id')->toArray()
            ]);
            return [];
        }

        $this->merchantPay = $merchant;

        return [
            'id' => $merchant->id,
            'alias' => $merchant->alias,
            'data' => $merchant->toArray(),
        ];
    }

    /**
     * Проверка лимитов мерчанта.
     *
     * @param object $merchant Мерчант для проверки.
     * @param object $merchantData Данные по лимитам.
     * @return bool
     *
     * Пример использования:
     * if ($this->passesMerchantLimits($merchant, $merchantData)) {
     *     // Мерчант удовлетворяет лимитам
     * }
     * @throws MathException
     */
    protected function passesMerchantLimits($merchant, $merchantData): bool
    {
        // Лимиты-количества (0 — валидный оверрайд «без лимита»)
        $dailyOrderLimit   = (int)($merchantData->merchant_day_limit   ?? $merchant->day_limit_merchant   ?? 0);
        $monthlyOrderLimit = (int)($merchantData->merchant_month_limit ?? $merchant->month_limit_merchant ?? 0);

        // Денежные лимиты — строки под BigDecimal
        $dayLimitAmountStr   = (string)($merchantData->merchant_day_limit_amount    ?? $merchant->day_limit_amount_merchant    ?? '0');
        $monthLimitAmountStr = (string)($merchantData->merchant_month_limit_amount  ?? $merchant->month_limit_amount_merchant  ?? '0');
        $minOrderAmountStr   = (string)($merchantData->merchant_min_amount_for_order ?? $merchant->min_amount_for_per_order    ?? '0');
        $maxOrderAmountStr   = (string)($merchantData->merchant_max_amount_for_order ?? $merchant->max_amount_for_per_order    ?? '0');

        // Сумма текущей заявки
        $taskGiveStr = (string) $this->task->give_price;

        // Окна по времени
        $today = Carbon::today();
        $dailyStats = Task::where('id_merchant', $merchant->id)
            ->whereIn('status', [3, 4])
            ->whereBetween('created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
            ->selectRaw('COALESCE(SUM(give_price),0) as sum, COUNT(*) as count')
            ->first();

        $monthlyStats = Task::where('id_merchant', $merchant->id)
            ->whereIn('status', [3, 4])
            ->whereBetween('created_at', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])
            ->selectRaw('COALESCE(SUM(give_price),0) as sum, COUNT(*) as count')
            ->first();

        try {
            $sumDay   = BigDecimal::of((string)($dailyStats->sum   ?? '0'));
            $sumMonth = BigDecimal::of((string)($monthlyStats->sum ?? '0'));

            $limitDay   = BigDecimal::of($dayLimitAmountStr);
            $limitMonth = BigDecimal::of($monthLimitAmountStr);

            $minOrder = BigDecimal::of($minOrderAmountStr);
            $maxOrder = BigDecimal::of($maxOrderAmountStr);
            $taskAmt  = BigDecimal::of($taskGiveStr);

            $zero = BigDecimal::zero();
        } catch (\Throwable $e) {
            // Безопасная деградация: не блокируем мерчанта, но фиксируем событие
            Log::warning('passesMerchantLimits: неверный формат лимитов/сумм', [
                'task_id' => $this->task->id,
                'error'   => $e->getMessage(),
            ]);
            return true;
        }

        // Проверки сумм
        if ($limitDay->compareTo($zero) > 0   && $sumDay->compareTo($limitDay)   >= 0) return false;
        if ($limitMonth->compareTo($zero) > 0 && $sumMonth->compareTo($limitMonth) >= 0) return false;

        if ($minOrder->compareTo($zero) > 0   && $taskAmt->compareTo($minOrder)   < 0) return false;
        if ($maxOrder->compareTo($zero) > 0   && $taskAmt->compareTo($maxOrder)   > 0) return false;

        // Проверки по количеству
        $dayCount   = (int) ($dailyStats->count   ?? 0);
        $monthCount = (int) ($monthlyStats->count ?? 0);

        if ($dailyOrderLimit   > 0 && $dayCount   >= $dailyOrderLimit)   return false;
        if ($monthlyOrderLimit > 0 && $monthCount >= $monthlyOrderLimit) return false;

        return true;
    }

    /**
     * Единая точка проверки реквизитов мерчанта.
     *
     * Логика:
     * - если реквизит пустой → false
     * - если currency/merchant не определены → true (пропускаем)
     * - если для связки (currency ↔ merchant) выбран validator_type → валидируем
     *
     * @param string $accountNumber
     * @param string $source
     * @return bool
     */
    protected function validateMerchantAccountOnce(string $accountNumber, string $source): bool
    {
        $accountNumber = trim($accountNumber);
        if ($accountNumber === '') {
            $this->lastMerchantValidatorType = null;
            $this->lastMerchantValidatorPassed = false;
            return false;
        }

        // reset
        $this->lastMerchantValidatorType = null;
        $this->lastMerchantValidatorPassed = null;

        if (!$this->currencyIn instanceof Currency || !$this->merchantPay instanceof GatewayMerchant) {
            // не можем проверить — считаем, что можно выдавать
            $this->lastMerchantValidatorPassed = true;
            return true;
        }

        $validator = app(MerchantAccountValidator::class);

        $type = trim((string) $validator->getValidatorType($this->currencyIn, $this->merchantPay));
        $this->lastMerchantValidatorType = ($type !== '') ? $type : null;

        // Валидатор не выбран → считаем проверку пройденной
        if ($type === '') {
            $this->lastMerchantValidatorPassed = true;
            return true;
        }

        $passed = $validator->validateIfConfigured(
            $this->currencyIn,
            $this->merchantPay,
            $accountNumber,
            ['task_id' => $this->task->id ?? null, 'source' => $source]
        );

        $this->lastMerchantValidatorPassed = $passed;

        // Если всё ок — выдаём
        if ($passed) {
            return true;
        }

        // Провал — применяем стратегию валюты
        $strategy = $this->getMerchantValidatorFailStrategy();

        if ($strategy === 0) {
            return true;
        }

        return false;
    }

    /**
     * 0 — IGNORE (выдаём реквизиты даже если провал)
     * 1 — HARD_FAIL (не выдаём реквизиты если провал)
     *
     * Читаем из БД, потому что currencyIn может быть загружена без новой колонки.
     */
    protected function getMerchantValidatorFailStrategy(): int
    {
        $currencyId = (int) ($this->currencyIn->id ?? 0);
        if ($currencyId <= 0) {
            return 1;
        }

        $dbValue = Currency::query()
            ->whereKey($currencyId)
            ->value('merchant_validator_fail_strategy');

        $strategy = is_numeric($dbValue) ? (int) $dbValue : 1;

        return in_array($strategy, [0, 1], true) ? $strategy : 1;
    }
}
