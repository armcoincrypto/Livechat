<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Engine;

use iEXPackages\Payments\Core\Config\GatewayConfig;
use iEXPackages\Payments\Core\Contracts\RequestInterface;
use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use RuntimeException;

/**
 * Абстрактная базовая реализация ResponseInterface.
 *
 * Содержит:
 *  - ссылку на Request;
 *  - ответ сервера (data);
 *  - входные параметры (query);
 *  - GatewayConfig (config.php шлюза);
 *  - базовую логику статусов (isFailure через isSuccessful/isPending/isCancelled);
 *  - helper-методы для работы с данными и приведения типов.
 *
 * НЕ навязывает дополнительные методы интерфейсу (getStatus, getAmount, getBankName и т.п.),
 * но ты можешь реализовывать их в конкретных класcах, а IDE будет знать про них через
 *
 */
abstract class AbstractResponse implements ResponseInterface
{
    /**
     * Request, который породил этот Response.
     */
    protected RequestInterface $request;

    /**
     * Ответ платёжного сервиса в виде массива.
     */
    protected array $data = [];

    /**
     * Входные параметры (payload), отправленные в шлюз.
     */
    protected array $query = [];

    /**
     * Статический config.php шлюза.
     */
    protected GatewayConfig $definition;

    /**
     * @param RequestInterface $request  Request, создавший ответ
     * @param array            $data     Ответ сервера (шлюза)
     * @param array|null       $query    Входные параметры (null → берём из Request::getParameters())
     */
    public function __construct(
        RequestInterface $request,
        array            $data = [],
        ?array           $query = null,
    ) {
        $this->request = $request;
        $this->data    = $data;
        $this->query   = $query ?? $request->getParameters();

//        if (!method_exists($request, 'getGatewayConfig')) {
//            throw new RuntimeException(
//                'Request должен реализовывать getGatewayConfig() для использования с AbstractResponse.'
//            );
//        }
//
//        /** @var GatewayConfig $cfg */
//        $cfg = $request->getGatewayConfig();
//        $this->definition = $cfg;
    }

    // ---------------------------------------------------------------------
    //  ДОСТУП К REQUEST / CONFIG / MERCHANT / TASK
    // ---------------------------------------------------------------------

    /**
     * Получить исходный Request.
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Получить GatewayConfig (config.php текущего шлюза).
     */
    public function getGatewayConfig(): GatewayConfig
    {
        return $this->definition;
    }

    /**
     * Конфиг мерчанта (если AbstractRequest реализует getMerchantConfig()).
     */
    public function getMerchantConfig(): array
    {
        if (method_exists($this->request, 'getMerchantConfig')) {
            /** @var array $config */
            $config = $this->request->getMerchantConfig();
            return $config;
        }

        return [];
    }

    /**
     * Модель мерчанта (GatewayMerchant) при наличии.
     */
    public function getMerchant(): ?object
    {
        if (method_exists($this->request, 'getMerchant')) {
            $merchant = $this->request->getMerchant();
            return $merchant;
        }

        return null;
    }

    /**
     * Модель заявки/задачи (Task) при наличии.
     */
    public function getTask(): ?object
    {
        if (method_exists($this->request, 'getTask')) {
            /** @var object|null $task */
            $task = $this->request->getTask();
            return $task;
        }

        return null;
    }

    // ---------------------------------------------------------------------
    //  DATA / QUERY / RAW
    // ---------------------------------------------------------------------

    /**
     * Ответ сервера (шлюза) в виде массива.
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Входные параметры запроса (payload).
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * Сырой ответ. Для совместимости эквивалентен getData().
     */
    public function getRaw(): mixed
    {
        return $this->data;
    }

    /**
     * Получить значение из ответа сервера по ключу (поддерживает dot-notation).
     */
    protected function dataGet(string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return $this->data;
        }

        if (!str_contains($key, '.')) {
            return $this->data[$key] ?? $default;
        }

        $segments = explode('.', $key);
        $value    = $this->data;

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Проверить наличие ключа в ответе сервера (поддерживает dot-notation).
     */
    protected function dataHas(string $key): bool
    {
        if ($key === '') {
            return !empty($this->data);
        }

        if (!str_contains($key, '.')) {
            return array_key_exists($key, $this->data);
        }

        $segments = explode('.', $key);
        $value    = $this->data;

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Получить значение из входных параметров по ключу.
     */
    protected function queryGet(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Проверить наличие ключа во входных параметрах.
     */
    protected function queryHas(string $key): bool
    {
        return array_key_exists($key, $this->query);
    }

    /**
     * Алиас для queryGet() — для совместимости со старым кодом.
     */
    protected function param(string $key, mixed $default = null): mixed
    {
        return $this->queryGet($key, $default);
    }

    // ---------------------------------------------------------------------
    //  SAFE CAST HELPERS
    // ---------------------------------------------------------------------

    /**
     * Безопасно привести значение к строке.
     */
    protected function safeString(mixed $value, ?string $default = null): ?string
    {
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        if (!is_string($value)) {
            return $default;
        }

        $value = trim($value);

        return $value === '' ? $default : $value;
    }

    /**
     * Безопасно привести значение к int.
     */
    protected function safeInt(mixed $value, ?int $default = null): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * Безопасно привести значение к bool.
     */
    protected function safeBool(mixed $value, ?bool $default = null): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $lower = strtolower($value);
            if (in_array($lower, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($lower, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return $default;
    }

    /**
     * Безопасно привести значение к "decimal" (строка с числом).
     */
    protected function safeDecimal(mixed $value, ?string $default = null): ?string
    {
        if ((is_string($value) || is_int($value) || is_float($value)) && is_numeric($value)) {
            return (string) $value;
        }

        return $default;
    }

    // ---------------------------------------------------------------------
    //  СТАТУСЫ (БАЗОВАЯ ЛОГИКА)
    // ---------------------------------------------------------------------

    /**
     * По умолчанию операция НЕ считается успешной.
     * Конкретный класс должен переопределить этот метод.
     */
    public function isSuccessful(): bool
    {
        return false;
    }

    /**
     * По умолчанию операция НЕ считается "в ожидании".
     */
    public function isPending(): bool
    {
        return false;
    }

    /**
     * По умолчанию операция НЕ считается "отменённой".
     */
    public function isCancelled(): bool
    {
        return false;
    }

    /**
     * Всё, что не successful/pending/cancelled — трактуем как ошибку.
     */
    public function isFailure(): bool
    {
        return !$this->isSuccessful()
            && !$this->isPending()
            && !$this->isCancelled();
    }


    public function getTransactionId()
    {
        return $this->request->getTransactionId() ?? '';
    }

    // ---------------------------------------------------------------------
    //  ОБОБЩЁННОЕ ПРЕДСТАВЛЕНИЕ
    // ---------------------------------------------------------------------

    /**
     * Универсальный массив для логов/отладки/API.
     *
     * ВНИМАНИЕ:
     *  - сюда попадают только гарантированные поля (из интерфейса),
     *  - дополнительные поля (status, amount и т.д.) ты сам добавляешь
     *    в конкретных реализациях, если нужно.
     */
    public function toArray(): array
    {
        return [
            'success' => $this->isSuccessful(),
            'pending' => $this->isPending(),
            'cancelled' => $this->isCancelled(),
            'failure' => $this->isFailure(),

            'data'  => $this->data,
            'query' => $this->query,
        ];
    }
}
