<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Services;

use App\Settings\BestChangeConfig;
use iEXPackages\BestChange\Contracts\BestChangeHttpClientInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\ConnectionException;

/**
 * BestChangeCatalogRepository
 *
 * Единая точка получения справочников BestChange с автокэшированием.
 *
 * Что решает:
 * - Любой вызов "дай города/валюты/обменники/страны" всегда работает одинаково:
 *   сначала кэш -> если нет/force -> запрос к API -> сохранить в кэш -> вернуть.
 * - Убирает дублирование логики кэша из команд и контроллеров.
 * - Обеспечивает масштабирование: далее можно добавить другие справочники, не меняя внешний API.
 */
final class BestChangeCatalogRepository
{
    public function __construct(
        private readonly BestChangeHttpClientInterface $client,
        private readonly BestChangeConfig $settings,
        private readonly CacheRepository $cache,
    ) {}

    /**
     * Язык справочников (по BestChangeConfig.site_version).
     */
    public function language(): string
    {
        $lang = strtolower(trim($this->settings->siteVersion()));
        return preg_match('/^[a-z]{2,5}$/', $lang) ? $lang : 'en';
    }

    /**
     * @return array<int, array> keyBy(id)
     * @throws ConnectionException
     */
    public function currencies(bool $forceRefresh = false): array
    {
        return $this->rememberIndexed(
            key: 'bestchange-api.currencies',
            ttlSeconds: 86400 * 10,
            forceRefresh: $forceRefresh,
            loader: fn() => $this->client->fetchCurrencies($this->language()),
            mapper: fn(array $row) => $this->mapCurrency($row),
        );
    }

    /**
     * @return array<int, array> keyBy(id) + full_name
     * @throws ConnectionException
     */
    public function cities(bool $forceRefresh = false): array
    {
        return $this->rememberIndexed(
            key: 'bestchange-api.cities',
            ttlSeconds: 86400 * 30,
            forceRefresh: $forceRefresh,
            loader: fn() => $this->client->fetchCities($this->language()),
            mapper: fn(array $row) => $this->mapCity($row),
        );
    }

    /**
     * @return array<int, array> keyBy(id)
     * @throws ConnectionException
     */
    public function countries(bool $forceRefresh = false): array
    {
        return $this->rememberIndexed(
            key: 'bestchange-api.countries',
            ttlSeconds: 86400 * 30,
            forceRefresh: $forceRefresh,
            loader: fn() => $this->client->fetchCountries($this->language()),
            mapper: null,
        );
    }

    /**
     * @return array<int, array> keyBy(id)
     * @throws ConnectionException
     */
    public function exchangers(bool $forceRefresh = false): array
    {
        return $this->rememberIndexed(
            key: 'bestchange-api.exchangers',
            ttlSeconds: 86400 * 10,
            forceRefresh: $forceRefresh,
            loader: fn() => $this->client->fetchChangers($this->language()),
            mapper: null,
        );
    }

    /**
     * @return array<int, array> keyBy(id)
     * @throws ConnectionException
     */
    public function groups(bool $forceRefresh = false): array
    {
        return $this->rememberIndexed(
            key: 'bestchange-api.groups',
            ttlSeconds: 86400 * 30,
            forceRefresh: $forceRefresh,
            loader: fn() => $this->client->fetchGroups($this->language()),
            mapper: null,
        );
    }

    /**
     * Прогреть все справочники одним вызовом.
     *
     * @throws ConnectionException
     */
    public function warmupAll(bool $forceRefresh = false): void
    {
        $this->currencies($forceRefresh);
        $this->cities($forceRefresh);
        $this->countries($forceRefresh);
        $this->exchangers($forceRefresh);
        $this->groups($forceRefresh);
    }

    /**
     * Общее автокэширование + индексирование по id.
     *
     * @param string $key
     * @param int $ttlSeconds
     * @param bool $forceRefresh
     * @param callable():array $loader
     * @param (callable(array):array)|null $mapper
     * @return array<int, array>
     * @throws ConnectionException
     */
    /**
     * Общее автокэширование + индексирование по id.
     *
     * Правила:
     * - forceRefresh=true: пытаемся обновить данные, но НЕ затираем кэш пустотой при ошибке/пустом ответе
     * - если loader вернул пусто/не массив: возвращаем старый кэш (если был), иначе []
     * - НЕ кэшируем пустые результаты
     *
     * @param string $key
     * @param int $ttlSeconds
     * @param bool $forceRefresh
     * @param callable():array $loader
     * @param (callable(array):array)|null $mapper
     * @return array<int, array>
     */
    private function rememberIndexed(
        string $key,
        int $ttlSeconds,
        bool $forceRefresh,
        callable $loader,
        ?callable $mapper
    ): array {
        $ttlSeconds = max(60, $ttlSeconds);

        /** @var array<int, array>|null $cached */
        $cached = $this->cache->get($key);

        // Если не форсим — возвращаем кэш как есть
        if (!$forceRefresh && is_array($cached) && $cached !== []) {
            return $cached;
        }

        // Если форсим — чистим, но держим старое значение как fallback
        if ($forceRefresh) {
            $this->cache->forget($key);
        }

        try {
            $raw = $loader();
        } catch (\Throwable) {
            // Не затираем кэш ошибкой
            return (is_array($cached) && $cached !== []) ? $cached : [];
        }

        if (!is_array($raw) || $raw === []) {
            // Не кэшируем пустоту и не убиваем старые данные
            return (is_array($cached) && $cached !== []) ? $cached : [];
        }

        $indexed = [];

        foreach ($raw as $row) {
            if (!is_array($row) || !isset($row['id'])) {
                continue;
            }

            $id = (int) $row['id'];
            if ($id <= 0) {
                continue;
            }

            $indexed[$id] = $mapper ? $mapper($row) : $row;
        }

        if ($indexed === []) {
            // Не кэшируем пустой индекс
            return (is_array($cached) && $cached !== []) ? $cached : [];
        }

        ksort($indexed);

        $this->cache->put($key, $indexed, $ttlSeconds);

        return $indexed;
    }
    /**
     * Добавляет валютах поле default_code.
     */
    private function mapCurrency(array $row): array
    {
        $name = (string)($row['name'] ?? '');
        preg_match('/\(?([^\s()]+)\)?$/', $name, $m);
        $row['default_code'] = $m[1] ?? '';
        return $row;
    }

    /**
     * Добавляет городам поле full_name.
     */
    private function mapCity(array $row): array
    {
        $name = (string)($row['name'] ?? '');
        $code = (string)($row['code'] ?? '');
        $row['full_name'] = $code !== '' ? "{$name} ({$code})" : $name;
        return $row;
    }
}
