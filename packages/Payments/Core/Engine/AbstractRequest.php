<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Engine;

use App\Models\GatewayMerchant;
use App\Models\GatewayPayment;
use App\Models\Task;
use iEXPackages\Payments\Core\Config\GatewayConfig;
use iEXPackages\Payments\Core\Contracts\GatewayHttpClientInterface;
use iEXPackages\Payments\Core\Contracts\RequestInterface;
use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use iEXPackages\Payments\Core\Exceptions\InvalidRequestException;
use iEXPackages\Payments\Core\Services\GatewaySoapClient;
use iEXPackages\Payments\Core\Support\NetworkCodeResolver;
use iEXPackages\Payments\Core\Support\PaymentNetworkCodeResolver;
use iEXPackages\Payments\Logging\GatewayLogContext;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Базовый Request для всех операций платёжных шлюзов.
 *
 * Здесь сосредоточено:
 *  - хранение входных параметров операции ($parameters)
 *  - доступ к конфигу мерчанта ($config, из Vault)
 *  - доступ к config.php шлюза ($definition)
 *  - стандартные поля: amount, currency, transactionId, description
 *  - Retry (повтор отправки) + хуки под идемпотентность
 *  - Валидация параметров: validate(), validateConfig(), validateConfigFields()
 *  - Возможность привязать модель мерчанта и модель заявки (GatewayMerchant, Task, ...)
 */
abstract class AbstractRequest implements RequestInterface
{
    /**
     * Конфиг мерчанта (из Vault).
     * Например:
     *  - api_key
     *  - merchant_id
     *  - callback_url
     *  - purchase_url
     *  и т.д.
     */
    protected Collection $config;


    /**
     * Кэш Accessor-ов для разных групп inputs: merchant, pay, ...
     *
     * @var array<string, InputsAccessor>
     */
    protected array $inputsAccessors = [];

    /**
     * Статический config.php шлюза (мета, capabilities, operations, inputs).
     */
    protected GatewayConfig $definition;

    /**
     * Входные параметры операции (amount, currency, transactionId, description, ...).
     */
    protected Collection $parameters;

    /**
     * Последний созданный Response.
     */
    protected ?ResponseInterface $response = null;

    /**
     * Модель мерчанта (GatewayMerchant или твой кастомный класс).
     */
    protected ?GatewayMerchant $merchant = null;
    protected ?GatewayPayment  $payment  = null;


    protected ?NetworkCodeResolver $networkResolver = null;
    protected ?PaymentNetworkCodeResolver $paymentNetworkResolver = null;

    /**
     * Модель заявки/Task, привязанная к этому запросу.
     */
    protected ?Task $task = null;

    /**
     * Настройки retry.
     */
    protected int $maxAttempts  = 1;  // по умолчанию: без повторов
    protected int $retryDelayMs = 0;  // задержка между попытками (мс)


    protected ?\iEXPackages\Payments\Core\Engine\AbstractGateway $gateway = null;

    public function __construct(
        Collection    $merchantConfig,
        GatewayConfig $definition,
        array         $parameters = [],
    ) {
        $this->config     = $merchantConfig;
        $this->definition = $definition;
        $this->parameters = collect($parameters);
    }


    public function withGateway(\iEXPackages\Payments\Core\Engine\AbstractGateway $gateway): static
    {
        $this->gateway = $gateway;
        return $this;
    }

    public function getGateway(): ?\iEXPackages\Payments\Core\Engine\AbstractGateway
    {
        return $this->gateway;
    }

    protected function isMerchantContext(): bool
    {
        return $this->merchant !== null;
    }

    protected function isPaymentContext(): bool
    {
        return $this->payment !== null;
    }

    // ---------------------------------------------------------------------
    //  БАЗОВАЯ РАБОТА С ПАРАМЕТРАМИ ОПЕРАЦИИ
    // ---------------------------------------------------------------------

    /**
     * Получить InputsAccessor для группы (merchant, pay, ...).
     *
     * Примеры:
     *   $this->inputs('merchant')->getApiKey();
     *   $this->inputs('merchant')->getWalletUniqueId();
     *   $this->inputs('pay')->getApiKey();
     */
    protected function inputs(string $group = 'merchant'): InputsAccessor
    {
        if (!isset($this->inputsAccessors[$group])) {
            $this->inputsAccessors[$group] = new InputsAccessor(
                merchantConfig: $this->config,
                definition:     $this->gatewayConfig(),
                group:          $group,
            );
        }

        return $this->inputsAccessors[$group];
    }

    public function initialize(array $parameters = []): static
    {
        $this->parameters = collect($parameters);

        return $this;
    }

    public function getParameters(): array
    {
        return $this->parameters->toArray();
    }

    public function getParameter(string $key, mixed $default = null): mixed
    {
        return $this->parameters->get($key, $default);
    }

    public function setParameter(string $key, mixed $value): static
    {
        $this->parameters->put($key, $value);

        return $this;
    }

    public function addParameters(array $parameters): static
    {
        foreach ($parameters as $key => $value) {
            $this->setParameter($key, $value);
        }

        return $this;
    }

    // ---------------------------------------------------------------------
    //  СТАНДАРТНЫЕ ПОЛЯ: amount, currency, transactionId, description
    // ---------------------------------------------------------------------

    public function setAmount(string $amount): static
    {
        return $this->setParameter('amount', $amount);
    }

    public function getAmount(?string $default = null): ?string
    {
        $value = $this->getParameter('amount', $default);

        return $value === null ? null : (string) $value;
    }

    public function setCurrency(string $currency): static
    {
        return $this->setParameter('currency', $currency);
    }

    public function getCurrency(?string $default = null): ?string
    {
        $value = $this->getParameter('currency', $default);

        return $value === null ? null : (string) $value;
    }


    /**
     * Get the request return URL.
     */
    public function getReturnUrl(): string
    {
        return $this->getParameter('returnUrl');
    }

    /**
     * Sets the request return URL.
     *
     * @return $this
     */
    public function setReturnUrl(string $value): static
    {
        return $this->setParameter('returnUrl', $value);
    }

    /**
     * Get the request cancel URL.
     */
    public function getCancelUrl(): string
    {
        return $this->getParameter('cancelUrl');
    }

    /**
     * Sets the request cancel URL.
     *
     * @return $this
     */
    public function setCancelUrl(string $value): static
    {
        return $this->setParameter('cancelUrl', $value);
    }

    /**
     * Get the request notify URL.
     */
    public function getNotifyUrl(): string
    {
        return $this->getParameter('notifyUrl');
    }

    /**
     * Sets the request notify URL.
     *
     * @return $this
     */
    public function setNotifyUrl(string $value): static
    {
        return $this->setParameter('notifyUrl', $value);
    }

    /**
     * Явно установить transactionId.
     */
    public function setTransactionId(string|int $id): static
    {
        $this->setParameter('transactionId', (string) $id);

        return $this;
    }

    /**
     * Получить transactionId.
     *
     * Приоритет:
     *  1) Явно установленный через setTransactionId()
     *  2) ID привязанной Task (если есть)
     *  3) $default
     */
    public function getTransactionId(?string $default = null): ?string
    {
        // 1) Явно заданный transactionId
        $value = $this->getParameter('transactionId');

        if ($value !== null && $value !== '') {
            return (string) $value;
        }

        // 2) Fallback на Task->id
        if (method_exists($this, 'getTask')) {
            $task = $this->getTask();

            if ($task && isset($task->id)) {
                return (string) $task->id;
            }
        }

        // 3) Default
        return $default;
    }

    public function setDescription(string $description): static
    {
        return $this->setParameter('description', $description);
    }

    public function getDescription(?string $default = null): ?string
    {
        $value = $this->getParameter('description', $default);

        return $value === null ? null : (string) $value;
    }

    // ---------------------------------------------------------------------
    //  ПРИВЯЗКА МОДЕЛЕЙ: MERCHANT, TASK
    // ---------------------------------------------------------------------

    public function withMerchant(GatewayMerchant $merchant): static
    {
        if ($this->payment !== null) {
            throw new LogicException('Gateway cannot have both merchant and payment context');
        }

        $this->merchant = $merchant;
        return $this;
    }

    public function withPayment(GatewayPayment $payment): static
    {
        if ($this->merchant !== null) {
            throw new LogicException('Gateway cannot have both merchant and payment context');
        }

        $this->payment = $payment;
        return $this;
    }

    protected function getMerchant(): ?GatewayMerchant
    {
        return $this->merchant;
    }

    protected function getPayment(): ?GatewayPayment
    {
        return $this->payment;
    }

    /**
     * Кэш дополнительных полей заявки (по типу: in/out и alias: currency).
     *
     * @var array<string, array<string, string>>
     */
    protected array $taskFieldsCache = [];

    /**
     * Получить дополнительные поля заявки по типу (in/out) и alias (например, currency).
     *
     * Возвращает массив вида:
     *  ['field_key' => 'field_value', ...]
     *
     * @param 'in'|'out' $type
     * @param string $alias
     */
    protected function getTaskFields(string $type, string $alias = 'currency'): array
    {
        $task = $this->getTask();
        if (!$task) {
            return [];
        }

        $cacheKey = $type . ':' . $alias;
        if (isset($this->taskFieldsCache[$cacheKey])) {
            return $this->taskFieldsCache[$cacheKey];
        }

        // Пытаемся использовать готовые отношения
        if ($alias === 'currency') {
            if ($type === 'in') {
                // relationLoaded оптимизация
                $items = $task->relationLoaded('tasks_fields_currency_in')
                    ? $task->tasks_fields_currency_in
                    : $task->tasks_fields_currency_in()->get();

                return $this->taskFieldsCache[$cacheKey] = $items
                    ->keyBy('field_key')
                    ->pluck('field_value', 'field_key')
                    ->toArray();
            }

            if ($type === 'out') {
                $items = $task->relationLoaded('tasks_fields_currency_out')
                    ? $task->tasks_fields_currency_out
                    : $task->tasks_fields_currency_out()->get();

                return $this->taskFieldsCache[$cacheKey] = $items
                    ->keyBy('field_key')
                    ->pluck('field_value', 'field_key')
                    ->toArray();
            }
        }

        // Если в будущем появятся другие alias — можно расширить через универсальную relation:
        // $items = $task->taskFields()->where('type_field', $type)->where('alias', $alias)->get();
        // return ...;

        return $this->taskFieldsCache[$cacheKey] = [];
    }

    /**
     * Доп. поля для "in" валюты (alias=currency).
     * Аналог твоего buyAdditionalFields (если ты покупку считаешь in/out иначе — переименуй).
     */
    protected function getCurrencyInFields(): array
    {
        return $this->getTaskFields('in', 'currency');
    }

    /**
     * Доп. поля для "out" валюты (alias=currency).
     */
    protected function getCurrencyOutFields(): array
    {
        return $this->getTaskFields('out', 'currency');
    }

    /**
     * Получить конкретное поле из out-части по ключу.
     */
    protected function getCurrencyOutField(string $key, ?string $default = null): ?string
    {
        $fields = $this->getCurrencyOutFields();
        return $fields[$key] ?? $default;
    }

    /**
     * Получить конкретное поле из in-части по ключу.
     */
    protected function getCurrencyInField(string $key, ?string $default = null): ?string
    {
        $fields = $this->getCurrencyInFields();
        return $fields[$key] ?? $default;
    }

    /**
     * Привязать модель заявки/задачи (Task).
     */
    public function withTask(Task $task): static
    {
        $this->task = $task;

        return $this;
    }

    /**
     * Получить модель заявки/задачи внутри Request.
     */
    public function getTask(): ?Task
    {
        return $this->task;
    }

    // ---------------------------------------------------------------------
    //  ДОСТУП К config.php ШЛЮЗА
    // ---------------------------------------------------------------------

    protected function gatewayConfig(): GatewayConfig
    {
        return $this->definition;
    }


    // ---------------------------------------------------------------------
    //  RETRY + ИДЕМПОТЕНТНОСТЬ
    // ---------------------------------------------------------------------

    /**
     * Включить retry: количество попыток и задержка между ними (мс).
     */
    public function withRetries(int $attempts, int $delayMs = 0): static
    {
        $this->maxAttempts  = max(1, $attempts);
        $this->retryDelayMs = max(0, $delayMs);

        return $this;
    }

    final public function send(): ResponseInterface
    {
        $data = $this->filterData($this->getData());

        // Идемпотентность
        $key = $this->resolveIdempotencyKey($data);

        if ($key !== null) {
            $cached = $this->loadIdempotentResponse($key);
            if ($cached instanceof ResponseInterface) {
                // Хук afterSend для кэшированных ответов тоже имеет смысл
                $this->afterSend($cached, $data);

                return $this->response = $cached;
            }
        }

        $attempt  = 0;
        $lastEx   = null;
        $response = null;

        // --- Вызов beforeSend ОДИН РАЗ перед первой попыткой ---
        $this->beforeSend($data);

        while ($attempt < $this->maxAttempts) {
            $attempt++;

            try {
                $response = $this->sendData($data);

                if ($key !== null) {
                    $this->storeIdempotentResponse($key, $response);
                }

                // --- Вызов afterSend при успешном ответе ---
                $this->afterSend($response, $data);

                return $this->response = $response;
            } catch (Throwable $e) {
                $lastEx = $e;

                if (!$this->shouldRetryOnException($e) || $attempt >= $this->maxAttempts) {
                    // Можно логировать ошибку тут или в shouldRetryOnException
                    throw $e;
                }

                if ($this->retryDelayMs > 0) {
                    usleep($this->retryDelayMs * 1000);
                }
            }
        }

        if ($lastEx) {
            throw $lastEx;
        }

        throw new \RuntimeException('Unknown error in AbstractRequest::send()');
    }

    /**
     * Хук перед отправкой (после getData() и filterData()).
     * По умолчанию ничего не делает.
     *
     * Можно переопределить в проектном базовом Request, чтобы логировать входные данные.
     */
    protected function beforeSend(array $data): void
    {
        // noop
    }

    /**
     * Хук после получения Response (успешного или из идемпотентного кэша).
     * По умолчанию ничего не делает.
     *
     * Можно переопределить для логирования ответа.
     */
    protected function afterSend(ResponseInterface $response, array $data): void
    {
        // noop
    }

    /**
     * Определяет, стоит ли повторять запрос при исключении.
     * По умолчанию false — можно переопределить в проектном базовом Request.
     */
    protected function shouldRetryOnException(Throwable $e): bool
    {
        return false;
    }

    /**
     * Идемпотентный ключ. По умолчанию отключено.
     * Можно переопределить в конкретном Request:
     *
     *   return $data['transactionId'] ?? null;
     */
    protected function resolveIdempotencyKey(array $data): ?string
    {
        return null;
    }

    /**
     * Загрузка идемпотентного ответа. По умолчанию пусто, реализуешь в проекте (Redis/БД).
     */
    protected function loadIdempotentResponse(string $key): ?ResponseInterface
    {
        return null;
    }

    /**
     * Сохранение идемпотентного ответа. По умолчанию пусто.
     */
    protected function storeIdempotentResponse(string $key, ResponseInterface $response): void
    {
        // noop
    }

    // ---------------------------------------------------------------------
    //  ВАЛИДАЦИЯ: ПАРАМЕТРЫ + КОНФИГ
    // ---------------------------------------------------------------------

    /**
     * Проверяет наличие и непустоту указанных параметров операции.
     *
     * Пример:
     *   $this->validate('amount', 'currency', 'transactionId');
     *
     * @throws InvalidRequestException
     */
    public function validate(string ...$keys): void
    {
        foreach ($keys as $key) {
            if (!$this->parameters->has($key)) {
                throw new InvalidRequestException("The [{$key}] parameter is required");
            }

            $value = $this->parameters->get($key);

            if ($value === null || $value === '') {
                throw new InvalidRequestException("The [{$key}] parameter must not be empty");
            }
        }
    }

    /**
     * Проверяет наличие ключей в конфиге мерчанта ($this->config).
     *
     * Пример:
     *   $this->validateConfig('merchant_id', 'callback_url');
     *
     * @throws InvalidRequestException
     */
    public function validateConfig(string ...$keys): void
    {
        foreach ($keys as $key) {
            if (!$this->config->has($key)) {
                throw new InvalidRequestException("Merchant config key [{$key}] is required");
            }

            $value = $this->config->get($key);

            if ($value === null || $value === '') {
                throw new InvalidRequestException("Merchant config key [{$key}] must not be empty");
            }
        }
    }

    /**
     * Авто-валидация конфиг-полей из config.php (inputs.{group}.fields/options_fields).
     *
     * Если в config.php у поля стоит 'required' => true, то здесь проверяется,
     * что в $this->config есть ключ с таким именем и он не пустой.
     *
     * Пример:
     *   $this->validateConfigFields('merchant');
     *
     * @throws InvalidRequestException
     */
    public function validateConfigFields(string $group): void
    {
        $cfg    = $this->gatewayConfig();
        $fields = $cfg->fields($group);
        $opt    = $cfg->optionFields($group);

        $requiredKeys = collect([$fields, $opt])
            ->flatten(1)
            ->filter(static fn (array $field) => ($field['required'] ?? false) === true)
            ->pluck('key')
            ->filter()
            ->unique()
            ->values();

        foreach ($requiredKeys as $key) {
            if (!$this->config->has($key)) {
                throw new InvalidRequestException("Merchant config key [{$key}] (group [{$group}]) is required");
            }

            $value = $this->config->get($key);

            if ($value === null || $value === '') {
                throw new InvalidRequestException("Merchant config key [{$key}] (group [{$group}]) must not be empty");
            }
        }
    }

    // ---------------------------------------------------------------------
    //  HELPER: ФИЛЬТРАЦИЯ ДАННЫХ + БЕЗОПАСНЫЕ GETTERS ИЗ CONFIG
    // ---------------------------------------------------------------------

    protected function filterData(array $data): array
    {
        return collect($data)
            ->filter(static fn ($v) => $v !== null && $v !== '')
            ->toArray();
    }

    /**
     * Безопасно достаёт строку из конфига мерчанта ($this->config).
     */
    protected function configString(string $key, string $default = ''): string
    {
        $value = $this->config->get($key, $default);

        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $default;
    }

    protected function networkResolver(): NetworkCodeResolver
    {
        if (!$this->networkResolver instanceof NetworkCodeResolver) {
            $this->networkResolver = app(NetworkCodeResolver::class);
        }

        return $this->networkResolver;
    }

    /**
     * Получить network_code для мерчанта и записи в meta заявки.
     *
     * Логика 1–5 сохраняется, плюс:
     *  - результат пишется в $task->meta->update(['merchant_network_code' => ...])
     */
    protected function getMerchantNetworkCode(bool $default_currency = true): string
    {
        /** @var GatewayMerchant|null $merchant */
        $merchant = $this->getMerchant();
        /** @var Task|null $task */
        $task     = $this->getTask();

        // Если не привязан мерчант или заявка — ведём себя как раньше
        $fallback = $default_currency ? ($this->getCurrency() ?? '') : '';

        if (!$merchant || !$task) {
            return $fallback;
        }
        // Передаём в резолвер именно СТРОКУ, а не bool
        return $this->networkResolver()->resolveAndStore($merchant, $task, $fallback);
    }

    protected function paymentNetworkResolver(): PaymentNetworkCodeResolver
    {
        return $this->paymentNetworkResolver ??= app(PaymentNetworkCodeResolver::class);
    }

    /**
     * Твой метод для выплат (имя оставляем).
     */
    protected function getPaymentNetworkCode(bool $default_currency = true): string
    {
        $payment = $this->getPayment();
        $task    = $this->getTask();

        if (!$payment || !$task) {
            return $default_currency ? (string) ($this->getCurrency() ?? '') : '';
        }

        return $this->paymentNetworkResolver()->resolve($payment, $task, $default_currency);
    }

    // ---------------------------------------------------------------------
    //  АБСТРАКТНЫЕ МЕТОДЫ, КОТОРЫЕ ДОЛЖЕН РЕАЛИЗОВАТЬ КАЖДЫЙ REQUEST
    // ---------------------------------------------------------------------

    /**
     * Режим получения endpoint base URL.
     *
     * STATIC  — берём из endpointStaticBaseUrl()
     * CONFIG  — берём из merchant config по ключу endpointConfigKey()
     * CUSTOM  — берём из endpointCustomBaseUrl() (полная кастомная логика)
     */
    protected function endpointMode(): string
    {
        return 'STATIC';
    }

    /**
     * Статический base URL для режима STATIC.
     * Пример: 'https://api.some-gateway.com'
     */
    protected function endpointStaticBaseUrl(): string
    {
        return '';
    }

    /**
     * Ключ в конфиге мерчанта (Vault), из которого берём endpoint для режима CONFIG.
     *
     * Может быть:
     *  - 'api_base_url' (полный URL)
     *  - 'api_host'     (host без схемы)
     */
    protected function endpointConfigKey(): string
    {
        // По умолчанию пробуем полный URL
        return 'api_base_url';
    }

    /**
     * Кастомная логика base URL для режима CUSTOM.
     * Тут можно строить URL из нескольких ключей, режима, окружения и т.п.
     */
    protected function endpointCustomBaseUrl(): string
    {
        return '';
    }

    /**
     * Префикс пути (если шлюз использует общий prefix).
     * Пример: '/open' или '/v1'
     */
    protected function endpointPathPrefix(): string
    {
        return '';
    }

    /**
     * Финальный base URL, нормализованный (без trailing slash).
     */
    protected function endpointBaseUrl(): string
    {
        $mode = strtoupper($this->endpointMode());

        $base = match ($mode) {
            'STATIC' => $this->endpointStaticBaseUrl(),
            'CUSTOM' => $this->endpointCustomBaseUrl(),
            default  => $this->endpointFromConfig(),
        };

        $base = trim((string) $base);

        if ($base === '') {
            return '';
        }

        // Если base — это host без схемы, добавим https://
        if (!str_starts_with($base, 'http://') && !str_starts_with($base, 'https://')) {
            $base = 'https://' . $base;
        }

        return rtrim($base, '/');
    }

    /**
     * Получение base URL из merchant config по ключу endpointConfigKey().
     */
    protected function endpointFromConfig(): string
    {
        $key = $this->endpointConfigKey();

        // 1) Если ключ — полный base_url
        $val = $this->configString($key);
        if ($val !== '') {
            return $val;
        }

        // 2) Если ключом был api_base_url, но он пустой — попробуем api_host как fallback
        if ($key !== 'api_host') {
            $host = $this->configString('api_host');
            if ($host !== '') {
                return $host;
            }
        }

        return '';
    }

    /**
     * Собирает финальный URL для запроса:
     * baseURL + prefix + path
     */
    protected function endpointUrl(string $path): string
    {
        $base = $this->endpointBaseUrl();
        $prefix = trim($this->endpointPathPrefix(), '/');
        $path = trim($path, '/');

        $fullPath = $prefix !== '' ? ($prefix . '/' . $path) : $path;

        return $base !== ''
            ? ($base . '/' . $fullPath)
            : $fullPath;
    }

    /**
     * Получить идентификатор входящего платежа для дальнейшего трекинга (проверка статуса/колбэк).
     *
     * Используется ТОЛЬКО для incoming/purchase/fetch_purchase (проверка приёма).
     *
     * Ищет значение в параметрах запроса по ключам:
     *  - transaction_id
     *  - payment_id
     *  - purchase_id
     *  - externalId
     *  - external_id
     *  - id
     */
    protected function getIncomingTrackingId(): ?string
    {
        $keys = [
            'transaction_id',
            'payment_id',
            'purchase_id',
            'externalId',
            'external_id',
            'id',
        ];

        foreach ($keys as $key) {
            $value = $this->getParameter($key);

            if ($value === null) {
                continue;
            }

            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Требует идентификатор входящего платежа для проверки статуса.
     *
     * @throws InvalidArgumentException
     */
    protected function requireIncomingTrackingId(
        string $message = 'Не передан идентификатор входящего платежа для проверки статуса.'
    ): string {
        $id = $this->getIncomingTrackingId();

        if ($id === null) {
            throw new InvalidArgumentException($message);
        }

        return $id;
    }


    /**
     * Получить идентификатор выплаты для дальнейшего трекинга (polling/callback).
     *
     * Используется ТОЛЬКО для payout/fetch_payout.
     *
     * Ищет значение в параметрах запроса по ключам:
     *  - withdrawal_id
     *  - withdrawRecordId
     *  - externalId
     *  - id
     */
    protected function getPayoutTrackingId(): ?string
    {
        $keys = [
            'withdrawal_id',
            'withdrawRecordId',
            'externalId',
            'id',
        ];

        foreach ($keys as $key) {
            $value = $this->getParameter($key);

            if ($value === null) {
                continue;
            }

            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Требует идентификатор выплаты для трекинга.
     *
     * @throws InvalidArgumentException
     */
    protected function requirePayoutTrackingId(
        string $message = 'Не передан идентификатор выплаты для проверки статуса.'
    ): string {
        $id = $this->getPayoutTrackingId();

        if ($id === null) {
            throw new InvalidArgumentException($message);
        }

        return $id;
    }

    protected function payString(string $key, string $default = ''): string
    {
        $payment = $this->getPayment();

        $ext = is_array($payment?->ext_options ?? null) ? $payment->ext_options : [];
        $value = $ext[$key] ?? $default;

        return (is_string($value) || is_int($value) || is_float($value))
            ? (string) $value
            : $default;
    }

    protected function merchantString(string $key, string $default = ''): string
    {
        $payment = $this->getMerchant();

        $ext = is_array($payment?->ext_options ?? null) ? $payment->ext_options : [];
        $value = $ext[$key] ?? $default;

        return (is_string($value) || is_int($value) || is_float($value))
            ? (string) $value
            : $default;
    }

    protected function httpClient(): GatewayHttpClientInterface
    {
        return app(GatewayHttpClientInterface::class);
    }

    /**
     * HTTP-настройки конкретного шлюза.
     * Переопределяется в Messages\AbstractRequest шлюза.
     */
    protected function httpConfig(): array
    {
        throw new \LogicException('httpConfig() must be implemented in gateway request');
    }

    protected function buildLogContext(
        string $direction,
        string $operation
    ): GatewayLogContext {
        return new GatewayLogContext(
            gatewayAlias: (string) $this->gatewayConfig()->alias(),
            direction: $direction,
            operation: $operation,
            taskId: $this->getTask()?->id ? (int) $this->getTask()->id : null,
            merchantId: $this->getMerchant()?->id ? (int) $this->getMerchant()->id : null,
            paymentId: $this->getPayment()?->id ? (int) $this->getPayment()->id : null,
            transactionId: $this->getTransactionId(),
            attempt: (int) $this->getParameter('attempt', 1),
        );
    }

    protected function sendRequest(
        string $method,
        string $path,
        array $data = [],
        string $format = 'asJson'
    ): array {
        $config = array_replace([], $this->httpConfig());


        $operation = $this->resolveOperation();
        $direction = $this->resolveDirection();

        $ctx = $this->buildLogContext($direction, $operation);

        return $this->httpClient()->send(
            spec: $config,
            method: $method,
            path: $path,
            data: $data,
            ctx: $ctx,
            format: $format
        );
    }

    protected function resolveOperation(): string
    {
        $class = static::class;

        foreach ($this->gatewayConfig()->operations() as $operation => $cfg) {
            if (is_array($cfg) && ($cfg['request_class'] ?? null) === $class) {
                return (string) $operation;
            }
        }

        throw new \LogicException("Operation not defined in config.php for request: {$class}");
    }

    protected function resolveDirection(): string
    {
        $operation = $this->resolveOperation();
        $type = $this->gatewayConfig()->operationType($operation);

        if ($type === 'service') {
            return match ($operation) {
                'options' => 'options',
                'balance' => 'balance',
                default   => 'service',
            };
        }

        if (!in_array($type, ['incoming', 'outgoing'], true)) {
            throw new \LogicException("Invalid operation type for [{$operation}]: " . (string) $type);
        }

        return $type;
    }


    /**
     * Возвращает значение конфигурации с учётом контекста:
     * - merchant → getApiKey()
     * - pay      → getPayApiKey()
     *
     * Используется для токенов, ключей, секретов и т.д.
     */
    protected function getContextConfigString(
        string $merchantMethod,
        string $payMethod,
        string $default = ''
    ): string {
        if ($this->isMerchantContext()) {
            if (!method_exists($this, $merchantMethod)) {
                return $default;
            }

            $value = $this->{$merchantMethod}();
        } else {
            if (!method_exists($this, $payMethod)) {
                return $default;
            }

            $value = $this->{$payMethod}();
        }

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Вызывает метод с учётом контекста.
     *
     * Пример:
     *  - getConnectionValue('getPublicKey') -> merchant: getPublicKey(), pay: getPayPublicKey()
     *  - getConnectionValue('getApiKey')    -> merchant: getApiKey(),    pay: getPayApiKey()
     *
     * @param string $baseMethod Имя метода без префикса Pay (например 'getPublicKey')
     * @param mixed  $default    Что вернуть, если метод не найден/вернул null
     */
    protected function getConnectionValue(string $baseMethod, mixed $default = null): mixed
    {
        $baseMethod = trim($baseMethod);
        if ($baseMethod === '') {
            return $default;
        }

        // pay-контекст → пробуем getPayXxx()
        if ($this->isPaymentContext()) {
            $payMethod = 'getPay' . substr($baseMethod, 3); // getPublicKey -> getPayPublicKey
            if (method_exists($this, $payMethod)) {
                $v = $this->{$payMethod}();
                return $v !== null ? $v : $default;
            }
        }

        // merchant-контекст или fallback → getXxx()
        if (method_exists($this, $baseMethod)) {
            $v = $this->{$baseMethod}();
            return $v !== null ? $v : $default;
        }

        return $default;
    }

    /**
     * Строковый вариант (самый частый).
     */
    protected function getConnectionString(string $baseMethod, string $default = ''): string
    {
        $v = $this->getConnectionValue($baseMethod, null);

        return is_scalar($v) ? (string) $v : $default;
    }

    /**
     * Универсальная очистка строковых значений (адреса, карты, счёта, hash, id).
     *
     * Делает:
     * - удаляет пробелы и переносы строк
     * - при необходимости обрезает memo/tag
     * - оставляет только допустимые символы
     *
     * @param string|null $value Исходное значение
     * @param array{
     *     cut_after_separator?: bool,   // обрезать по : | , ;
     *     allow?: string                // regex-класс допустимых символов
     * } $options
     *
     * @return string|null
     */
    protected function cleanString(?string $value, array $options = []): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // 1. Убираем пробелы, табы, переносы строк
        $clean = preg_replace('/\s+/u', '', $value);

        // 2. Обрезаем memo / tag (address:tag | address|tag | address,tag)
        if (($options['cut_after_separator'] ?? true) === true) {
            $clean = preg_split('/[:|,;]/u', $clean)[0] ?? $clean;
        }

        // 3. Разрешённые символы
        // По умолчанию: ТОЛЬКО буквы и цифры
        $allow = $options['allow'] ?? 'a-zA-Z0-9';

        $clean = preg_replace('/[^' . $allow . ']/u', '', $clean);

        return $clean !== '' ? $clean : null;
    }

    protected function soapClient(): GatewaySoapClient
    {
        return app(GatewaySoapClient::class);
    }

    /**
     * SOAP-конфиг конкретного шлюза.
     * Переопределяется в Messages\AbstractRequest шлюза (например Volet).
     *
     * @return array{
     *   wsdl: string,
     *   options?: array
     * }
     */
    protected function soapConfig(): array
    {
        throw new LogicException('soapConfig() must be implemented in gateway request');
    }

    /**
     * Унифицированный SOAP вызов (с логированием + sandbox/replay).
     *
     * @return array<string,mixed>
     */
    protected function sendSoapRequest(string $soapMethod, array $payload): array
    {
        $operation = $this->resolveOperation();
        $direction = $this->resolveDirection();
        $ctx = $this->buildLogContext($direction, $operation);

        return $this->soapClient()->call(
            spec: $this->soapConfig(),
            method: $soapMethod,
            payload: $payload,
            ctx: $ctx
        );
    }


    /**
     * Собрать payload для внешнего запроса или обработчика (callback).
     */
    abstract public function getData(): array;

    /**
     * Реальный запрос / обработка callback + создание Response.
     *
     * Внутри обычно:
     *  - HTTP запрос (для purchase/payout/etc.)
     *  - или логика проверки подписи (для completePurchase)
     *  - new SomeResponse(...)
     *  - return $this->response = ...
     */
    abstract protected function sendData(array $data): ResponseInterface;
}
