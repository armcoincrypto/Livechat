<?php

declare(strict_types=1);

namespace iEXPackages\BinInspector\Drivers;

use iEXPackages\BinInspector\BinInspectorResult;
use iEXPackages\BinInspector\Contracts\BinDriverInterface;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Базовый драйвер для работы с BIN-провайдерами.
 *
 * Содержит общую логику:
 *  - нормализация номера карты;
 *  - выделение BIN;
 *  - кэширование результата по BIN;
 *  - единый формат данных и вспомогательные геттеры.
 */
abstract class AbstractDriver implements BinDriverInterface
{
    /**
     * Явный префикс для кеш-ключа.
     *
     * Если оставить null, префикс будет сгенерирован автоматически
     * на основе названия класса драйвера.
     *
     * Пример:
     *  - BinlistDriver → bin_inspector_binlist_
     *  - BinCodesDriver → bin_inspector_bincodes_
     *
     * @var string|null
     */
    protected ?string $cachePrefix = null;

    /**
     * Длина BIN (обычно 6 цифр).
     *
     * @var int
     */
    protected int $binLength = 6;

    /**
     * Время жизни кэша в секундах (по умолчанию 24 часа).
     *
     * @var int
     */
    protected int $cacheTtl = 86400;

    /**
     * API-ключ провайдера (если требуется).
     *
     * @var string|null
     */
    protected ?string $apiKey = null;

    /**
     * Флаг сохранения данных в кэш.
     *
     * @var bool
     */
    protected bool $saveData = true;

    /**
     * Полный номер карты (только цифры).
     *
     * @var string|null
     */
    protected ?string $cardNumber = null;

    /**
     * BIN, выделенный из номера карты (первые N цифр).
     *
     * @var string|null
     */
    protected ?string $bin = null;

    /**
     * Последний полученный результат.
     *
     * @var BinInspectorResult|null
     */
    protected ?BinInspectorResult $result = null;

    /**
     * HTTP-клиент Laravel.
     *
     * @var HttpFactory
     */
    protected HttpFactory $http;

    /**
     * @param HttpFactory|null $http HTTP-клиент (подставляется из контейнера, если не указан)
     */
    public function __construct(?HttpFactory $http = null)
    {
        $this->http = $http ?? app(HttpFactory::class);
    }

    /**
     * {@inheritdoc}
     */
    public function setCardNumber(string $cardNumber): static
    {
        $normalized = $this->normalizeCardNumber($cardNumber);

        $this->cardNumber = $normalized;
        $this->bin        = $normalized !== null
            ? mb_substr($normalized, 0, $this->binLength)
            : null;

        $this->result = null;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setApiKey(?string $apiKey): static
    {
        $this->apiKey = $apiKey ?: null;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setIsSaveData(int|bool $isSaveData): static
    {
        $this->saveData = (bool) $isSaveData;

        return $this;
    }

    /**
     * Нормализует номер карты:
     *  - оставляет только цифры;
     *  - проверяет минимальную длину (должно хватать на BIN);
     *  - ограничивает максимальную длину (по умолчанию 19 цифр).
     *
     * @param string $cardNumber
     * @return string|null Нормализованный номер или null, если номер некорректен
     */
    protected function normalizeCardNumber(string $cardNumber): ?string
    {
        $digits = preg_replace('/\D+/', '', $cardNumber) ?? '';

        if ($digits === '' || strlen($digits) < $this->binLength) {
            return null;
        }

        if (strlen($digits) > 19) {
            return null;
        }

        return $digits;
    }

    /**
     * Собирает уникальный ключ для кэша по BIN.
     *
     * Если $cachePrefix задан явно в драйвере — используется он.
     * Если нет — префикс строится автоматически по имени класса драйвера.
     *
     * Примеры:
     *  - BinlistDriver → bin_inspector_binlist_123456
     *  - MrBinDriver   → bin_inspector_mrbin_123456
     *
     * @param string $bin
     * @return string
     */
    protected function makeCacheKey(string $bin): string
    {
        if ($this->cachePrefix !== null) {
            return $this->cachePrefix.$bin;
        }

        // class_basename() — хелпер Laravel, возвращает короткое имя класса
        $baseName = class_basename(static::class); // например "BinlistDriver"
        $normalized = strtolower(preg_replace('/Driver$/', '', $baseName) ?: $baseName);

        return 'bin_inspector_'.$normalized.'_'.$bin;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch(): ?BinInspectorResult
    {
        if ($this->result !== null) {
            return $this->result;
        }

        if ($this->bin === null || mb_strlen($this->bin) < $this->binLength) {
            // Не удалось получить корректный BIN — выходим без запроса к API.
            return null;
        }

        $cacheKey = $this->makeCacheKey($this->bin);

        if ($this->saveData) {
            $cached = Cache::get($cacheKey);

            if ($cached instanceof BinInspectorResult) {
                return $this->result = $cached;
            }
        }

        try {
            $raw = $this->performRequest($this->bin);

            if (! is_array($raw) || $raw === []) {
                return null;
            }

            $result = $this->mapToResult($raw);

            if ($result !== null && $this->saveData) {
                Cache::put($cacheKey, $result, $this->cacheTtl);
            }

            return $this->result = $result;
        } catch (Throwable $e) {
            // Логируем только BIN и тип драйвера — номер карты не пишем никогда.
            logger()->warning('BinInspector driver error', [
                'driver'  => static::class,
                'bin'     => $this->bin,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Выполнить реальный HTTP-запрос к провайдеру.
     *
     * @param string $bin
     * @return array<mixed>|null
     */
    abstract protected function performRequest(string $bin): ?array;

    /**
     * Преобразовать сырые данные провайдера в BinInspectorResult.
     *
     * @param array<mixed> $data
     * @return BinInspectorResult|null
     */
    abstract protected function mapToResult(array $data): ?BinInspectorResult;

    /**
     * Выполнить GET-запрос.
     *
     * @param string               $url   URL-адрес
     * @param array<string, mixed> $query Параметры запроса
     * @return array<mixed>
     */
    protected function httpGet(string $url, array $query = []): array
    {
        $response = $this->http
            ->timeout(5)
            ->retry(2, 200)
            ->get($url, $query)
            ->throw();

        /** @var array<mixed>|null $json */
        $json = $response->json();

        return $json ?? [];
    }

    /**
     * Выполнить POST-запрос.
     *
     * @param string               $url  URL-адрес
     * @param array<string, mixed> $data Данные тела запроса
     * @return array<mixed>
     */
    protected function httpPost(string $url, array $data = []): array
    {
        $response = $this->http
            ->timeout(5)
            ->retry(2, 200)
            ->post($url, $data)
            ->throw();

        /** @var array<mixed>|null $json */
        $json = $response->json();

        return $json ?? [];
    }

    // ===== Реализация интерфейса через DTO =====

    public function getPaymentSystem(): ?string
    {
        return $this->fetch()?->paymentSystem;
    }

    public function getType(): ?string
    {
        return $this->fetch()?->type;
    }

    public function isValid(): bool
    {
        return $this->fetch()?->isValid ?? false;
    }

    public function getValidate(): bool
    {
        return $this->isValid();
    }

    public function getBrand(): ?string
    {
        return $this->fetch()?->brand;
    }

    public function getCountryName(): ?string
    {
        return $this->fetch()?->countryName;
    }

    public function getCurrency(): ?string
    {
        return $this->fetch()?->currency;
    }

    public function geCurrency(): ?string
    {
        return $this->getCurrency();
    }

    public function getBankName(): ?string
    {
        return $this->fetch()?->bankName;
    }

    public function getBankUrl(): ?string
    {
        return $this->fetch()?->bankUrl;
    }

    public function getBankSupportNumber(): ?string
    {
        return $this->fetch()?->bankPhone;
    }

    public function getRawData(): array
    {
        return $this->fetch()?->raw ?? [];
    }

    public function getData(): array
    {
        $result = $this->fetch();

        if ($result === null) {
            return [];
        }

        return [
            'phone'         => $result->bankPhone,
            'website'       => $result->bankUrl,
            'bankName'      => $result->bankName,
            'countryName'   => $result->countryName,
            'currencyName'  => $result->currency,
            'brandName'     => $result->brand,
            'typeName'      => $result->type,
            'paymentSystem' => $result->paymentSystem,
        ];
    }
}
