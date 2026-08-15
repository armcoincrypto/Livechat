<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Engine;

use App\Models\GatewayMerchant;
use App\Models\GatewayPayment;
use BadMethodCallException;
use iEXPackages\Payments\Core\Config\GatewayConfig;
use iEXPackages\Payments\Core\Contracts\GatewayInterface;
use iEXPackages\Payments\Core\Contracts\RequestInterface;
use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Базовый шлюз.
 *
 * - хранит динамический конфиг мерчанта ($parameters)
 * - подгружает статический config.php → GatewayConfig
 * - даёт удобный createRequest()/createApiRequest()
 * - метод run() вызывает методы purchase(), api(), payout() и т.п., если они есть
 */
abstract class AbstractGateway implements GatewayInterface
{
    protected Collection $parameters;
    protected ?GatewayConfig $definition = null;

    /**
     * Модель мерчанта, привязанная к этому gateway.
     */
    protected ?GatewayMerchant $merchant = null;
    protected ?GatewayPayment  $payment  = null;

    /**
     * Человеческое название шлюза (не обязательно, можно брать из config.php).
     */
    protected string $name = '';

    public function __construct(array $parameters = [])
    {
        $this->initialize($parameters);
    }


    public function withMerchant(GatewayMerchant $merchant): static
    {
        $this->merchant = $merchant;
        return $this;
    }

    public function withPayment(GatewayPayment $payment): static
    {
        $this->payment = $payment;
        return $this;
    }

    public function getMerchant(): ?GatewayMerchant
    {
        return $this->merchant;
    }

    public function getPayment(): ?GatewayPayment
    {
        return $this->payment;
    }


    public function initialize(array $parameters = [], bool $merge = false): static
    {
        $normalized = $this->normalizeMerchantConfig($parameters);

        if (isset($this->parameters) && $merge) {
            $this->parameters = $this->parameters->merge($normalized);
        } else {
            $this->parameters = collect($normalized);
        }

        return $this;
    }

    protected function normalizeMerchantConfig(array $parameters): array
    {
        if ($parameters === []) {
            return [];
        }

        $result = [];
        $ignored = [];

        foreach ($parameters as $key => $value) {

            // ❌ числовые или пустые ключи — мусор
            if (!is_string($key) || $key === '') {
                $ignored[] = $key;
                continue;
            }

            // legacy: иногда значение приходит массивом
            $result[$key] = is_array($value) ? reset($value) : $value;
        }

        // ⚠️ логируем, но НЕ падаем
        if ($ignored !== []) {
            \Log::warning('Merchant config normalized with ignored keys', [
                'ignored_keys' => $ignored,
                'original'     => $parameters,
                'normalized'   => $result,
            ]);
        }

        return $result;
    }

    public function gatewayConfig(): GatewayConfig
    {
        if ($this->definition instanceof GatewayConfig) {
            return $this->definition;
        }

        $ref  = new \ReflectionClass(static::class);
        $dir  = dirname($ref->getFileName());
        $file = $dir . '/config.php';

        if (!file_exists($file)) {
            throw new RuntimeException("Gateway config file [config.php] not found for " . static::class);
        }

        $config = require $file;

        if (!is_array($config)) {
            throw new RuntimeException("Gateway config [config.php] must return array for " . static::class);
        }

        return $this->definition = GatewayConfig::fromArray($config);
    }

    public function getAlias(): string
    {
        $alias = $this->gatewayConfig()->alias();

        if (!$alias) {
            throw new RuntimeException("Alias not defined in config.php for " . static::class);
        }

        return $alias;
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
        return $this->initialize($parameters, merge: true);
    }

    /**
     * Сахар: создание Request-объекта.
     */
    protected function createRequest(string $class, array $parameters = []): AbstractRequest
    {
        /** @var AbstractRequest $request */
        $request = new $class(
            $this->parameters,
            $this->gatewayConfig(),
            $parameters
        );

        // Если мерчант есть и Request умеет withMerchant() — прокинем модель внутрь
        if ($this->merchant !== null && method_exists($request, 'withMerchant')) {
            $request->withMerchant($this->merchant);
        }

        if ($this->payment !== null && method_exists($request, 'withPayment')) {
            $request->withPayment($this->payment);
        }

        if (method_exists($request, 'withGateway')) {
            $request->withGateway($this);
        }

        return $request;
    }


    /**
     * Аналогично, если нужен отдельный api()-реквест.
     */
    protected function createApiRequest(string $class, array $parameters = []): AbstractRequest
    {
        return $this->createRequest($class, $parameters);
    }

    /**
     * run('purchase', [...]) → вызывает purchase([...])->send()
     */
    public function run(string $operation, array $input = []): ResponseInterface
    {
        $opConfig = $this->gatewayConfig()->operationConfig($operation);

        if (empty($opConfig)) {
            throw new RuntimeException("Operation [{$operation}] is not declared in config.php for " . static::class);
        }

        $requestClass = $opConfig['request_class'] ?? null;
        if (!$requestClass || !class_exists($requestClass)) {
            throw new RuntimeException("Request class is not defined for operation [{$operation}] in config.php.");
        }

        $request = $this->createRequest($requestClass, $input);

        return $request->send();
    }

    /**
     * request('purchase', [...]) → вызывает purchase([...]) и возвращает Request.
     */
    public function request(string $operation, array $input = []): RequestInterface
    {
        $opConfig = $this->gatewayConfig()->operationConfig($operation);

        if (empty($opConfig)) {
            throw new RuntimeException("Operation [{$operation}] is not declared in config.php for " . static::class);
        }

        $requestClass = $opConfig['request_class'] ?? null;
        if (!$requestClass || !class_exists($requestClass)) {
            throw new RuntimeException("Request class is not defined for operation [{$operation}] in config.php.");
        }

        return $this->createRequest($requestClass, $input);
    }

    protected function operationToMethod(string $operation): string
    {
        // fetch_payment → fetchPayment
        $operation = trim($operation);
        $parts = explode('_', $operation);

        $method = array_shift($parts);
        foreach ($parts as $p) {
            $method .= ucfirst($p);
        }

        return $method;
    }

    public function __call(string $name, array $arguments): mixed
    {
        // включено ли авто
        $cfg = $this->gatewayConfig()->all();
        $auto = $this->gatewayConfig()->autoOperationsEnabled();

        if (!$auto) {
            throw new BadMethodCallException("Method {$name} does not exist in " . static::class);
        }

        // ожидаем: $gateway->purchase([...]) → аргумент 0 это массив
        $params = $arguments[0] ?? [];
        if (!is_array($params)) {
            $params = [];
        }

        // purchase → purchase
        // fetchPayment → fetch_payment
        $operation = $this->methodToOperation($name);

        // проверяем, что операция объявлена в config.php
        $opCfg = $this->gatewayConfig()->operationConfig($operation);


        if (empty($opCfg)) {
            throw new \BadMethodCallException(
                "Operation [{$operation}] is not declared in config.php for " . static::class
            );
        }

        return $this->request($operation, $params);
    }

    /**
     * Преобразует имя метода в ключ операции config.php
     * fetchPayment -> fetch_payment
     * completePurchase -> complete_purchase
     */
    protected function methodToOperation(string $method): string
    {
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $method));
        return $snake;
    }

    /**
     * Проверить, есть ли ключ в runtime-конфиге мерчанта (Vault) / payment-конфиге.
     * Поддерживает dot-notation: "callback.route_name".
     */
    public function hasConfig(string $key): bool
    {
        return Arr::has($this->parameters->all(), $key);
    }

    /**
     * Получить значение из runtime-конфига (dot-notation).
     */
    public function config(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->parameters->all(), $key, $default);
    }

    /**
     * Получить строку из runtime-конфига.
     */
    public function configString(string $key, string $default = ''): string
    {
        $v = $this->config($key, $default);

        return is_scalar($v) ? (string) $v : $default;
    }

    /**
     * Получить bool из runtime-конфига.
     */
    public function configBool(string $key, bool $default = false): bool
    {
        $v = $this->config($key, $default);

        return (bool) $v;
    }

    /**
     * Обязательный строковый ключ (кидает исключение, если пусто).
     */
    public function requireConfigString(string $key): string
    {
        $value = $this->configString($key, '');

        if ($value === '') {
            throw new RuntimeException("Required merchant config key [{$key}] is missing or empty.");
        }

        return $value;
    }

    public function asMerchant(): static
    {
        $this->context = self::CONTEXT_MERCHANT;
        return $this;
    }

    public function asPay(): static
    {
        $this->context = self::CONTEXT_PAY;
        return $this;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function isMerchantContext(): bool
    {
        return $this->context === self::CONTEXT_MERCHANT;
    }

    public function isPayContext(): bool
    {
        return $this->context === self::CONTEXT_PAY;
    }
}
