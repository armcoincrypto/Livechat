<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Traits;

use iEXPackages\DynamicConfig\ValueObjects\Scope;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Trait DynamicConfigReadTrait
 *
 * Отвечает за чтение настроек и кеширование:
 *  - get()/all()
 *  - typed API: bool/int/float/string/array/getAs()
 *  - featureEnabled() с rollout-поддержкой
 *  - кеширование по scope (in-memory, external, preload)
 *  - micro-cache
 *  - поиск по ключам/значениям.
 */
trait DynamicConfigReadTrait
{
    /**
     * Базовое чтение значения настройки с учётом уровней и языка.
     *
     * @param string      $key      Ключ настройки ("group.field" или просто "field").
     * @param mixed       $default  Значение по умолчанию.
     * @param Scope|null  $scope    Scope (если null — используется текущий).
     * @param string|null $locale   Явная локаль (если null — берётся из app()->getLocale()).
     *
     * @return mixed
     */
    public function get(
        string $key,
        mixed $default = null,
        ?Scope $scope = null,
        ?string $locale = null
    ): mixed {
        try {
            // ACL: ограничение чтения, если включено
            if ($this->enforceReadAccess && $this->access !== null) {
                $user = Auth::user();

                if (!$this->access->canRead($key, $user)) {
                    return $default;
                }
            }

            $scope    = $scope ?? $this->scopeResolver->currentScope();
            $settings = $this->getMergedSettingsForScope($scope);

            $value = Arr::get($settings, $key, $default);

            return $this->resolveLocalizedValue($value, $locale);
        } catch (\Throwable $e) {
            $maskedKey = $this->maskKey($key);
            Log::error('DynamicConfig: ошибка чтения настройки', [
                'key'     => $maskedKey,
                'message' => $e->getMessage(),
            ]);

            return $default;
        }
    }

    /**
     * Все настройки для scope.
     * Если $merged = true — объединить по всей цепочке уровней.
     *
     * @param Scope|null $scope
     * @param bool       $merged
     *
     * @return array<string,mixed>
     */
    public function all(?Scope $scope = null, bool $merged = true): array
    {
        try {
            $scope = $scope ?? $this->scopeResolver->currentScope();

            return $merged
                ? $this->getMergedSettingsForScope($scope)
                : $this->getSettingsForExactScope($scope);
        } catch (\Throwable $e) {
            Log::error('DynamicConfig: ошибка получения всех настроек', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Получить настройку как bool.
     */
    public function bool(string $key, bool $default = false, ?Scope $scope = null): bool
    {
        $value = $this->get($key, $default, $scope);

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return $default;
    }

    /**
     * Получить настройку как int.
     */
    public function int(string $key, int $default = 0, ?Scope $scope = null): int
    {
        $value = $this->get($key, $default, $scope);

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * Получить настройку как float.
     */
    public function float(string $key, float $default = 0.0, ?Scope $scope = null): float
    {
        $value = $this->get($key, $default, $scope);

        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    /**
     * Получить настройку как string (с учётом локали, если значение локализовано).
     */
    public function string(
        string $key,
        string $default = '',
        ?Scope $scope = null,
        ?string $locale = null
    ): string {
        $value = $this->get($key, $default, $scope, $locale);

        if (is_string($value)) {
            return $value;
        }

        if ($value === null) {
            return $default;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * Получить настройку как массив (array).
     *
     * Попытка декодировать JSON-строку, если исходное значение — string.
     *
     * @return array<mixed>
     */
    public function array(string $key, array $default = [], ?Scope $scope = null): array
    {
        $value = $this->get($key, $default, $scope);

        if (is_array($value)) {
            return $value;
        }

        // Попытка декодировать JSON-строку в массив
        if (is_string($value)) {
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

                return is_array($decoded) ? $decoded : $default;
            } catch (\Throwable) {
                return $default;
            }
        }

        return $default;
    }

    /**
     * Проверка включённости feature-флага.
     *
     * Ожидаемый формат значения по ключу "features.{featureKey}":
     *
     * 1) Простое bool-значение:
     *    true / false
     *
     * 2) Массив:
     *    [
     *        "enabled"         => true|false,
     *        "rollout_percent" => 0-100,   // опционально
     *    ]
     *
     * $subjectId используется для детерминированного распределения по проценту.
     */
    public function featureEnabled(
        string $featureKey,
        ?Scope $scope = null,
        ?string $subjectId = null
    ): bool {
        $configKey = 'features.' . $featureKey;
        $raw       = $this->get($configKey, null, $scope);

        // Простое bool-значение
        if (is_bool($raw)) {
            return $raw;
        }

        if (!is_array($raw)) {
            // Если ничего не настроено — по умолчанию считаем фичу выключенной
            return false;
        }

        $enabled        = $raw['enabled'] ?? false;
        $rolloutPercent = $raw['rollout_percent'] ?? ($raw['rollout'] ?? null);

        if (!is_bool($enabled)) {
            $enabled = (bool) $enabled;
        }

        // Если фича выключена — сразу false
        if ($enabled === false) {
            return false;
        }

        // Если процент не задан — фича включена для всех
        if ($rolloutPercent === null) {
            return true;
        }

        $rolloutPercent = (float) $rolloutPercent;

        if ($rolloutPercent <= 0.0) {
            return false;
        }

        if ($rolloutPercent >= 100.0) {
            return true;
        }

        // Детерминированный rollout: распределяем subjectId по "сегментам" 0..99
        $bucketId = $this->calculateRolloutBucket($featureKey, $subjectId ?? 'global');

        return $bucketId < (int) round($rolloutPercent);
    }

    /**
     * Расчёт детерминированного "бака" (0..99) для rollout.
     */
    private function calculateRolloutBucket(string $featureKey, string $subjectId): int
    {
        $hash = crc32($featureKey . '|' . $subjectId);

        return (int) ($hash % 100);
    }

    /**
     * Объединённые настройки по цепочке scope: global -> ... -> current.
     *
     * @return array<string,mixed>
     */
    private function getMergedSettingsForScope(Scope $scope): array
    {
        $chain  = $scope->chainToRoot();
        $merged = [];

        foreach ($chain as $item) {
            $layer  = $this->getSettingsForExactScope($item);
            $merged = array_replace_recursive($merged, $layer);
        }

        return $merged;
    }

    /**
     * Настройки только конкретного scope (без родителей),
     * с учётом стратегии кеширования.
     *
     * cache_mode:
     *  - none   — без внешнего кеша (но с in-memory кешом per-request);
     *  - scope  — lazy-кеш через Laravel Cache + локальный кеш;
     *  - preload — при первом обращении загружаем все scope в память одним запросом.
     *
     * @return array<string,mixed>
     */
    private function getSettingsForExactScope(Scope $scope): array
    {
        $scopeKey = $scope->cacheKey();
        $localKey = 'dynamic_config_scope:' . $scopeKey;

        // 0) In-memory кеш внутри одного запроса — всегда
        if (array_key_exists($localKey, $this->scopeSettingsCache)) {
            return $this->scopeSettingsCache[$localKey];
        }

        // 1) Режим preload — при первом обращении грузим все scope в память
        if ($this->cacheMode === 'preload') {
            if (!$this->preloadedAllScopes) {
                $this->preloadAllScopes();
            }

            if (array_key_exists($localKey, $this->localCache)) {
                $settings = $this->localCache[$localKey];
            } else {
                $settings = $this->storage->load($scope);
                $this->localCache[$localKey] = $settings;
            }

            $this->scopeSettingsCache[$localKey] = $settings;

            return $settings;
        }

        // 2) Режим scope (lazy-кеш через Laravel Cache)
        if ($this->cacheMode === 'scope') {
            // сначала проверяем локальный кеш процесса
            if (array_key_exists($localKey, $this->localCache)) {
                $settings = $this->localCache[$localKey];
            } else {
                if ($this->cacheTtl !== null && $this->cacheTtl > 0) {
                    $settings = $this->cache->remember(
                        $localKey,
                        $this->cacheTtl,
                        fn (): array => $this->storage->load($scope)
                    );
                } else {
                    $settings = $this->storage->load($scope);
                }

                $this->localCache[$localKey] = $settings;
            }

            // и в любом случае кладём в in-memory кеш per request
            $this->scopeSettingsCache[$localKey] = $settings;

            return $settings;
        }

        // 3) Режим none — без ВНЕШНЕГО кеша, но с in-memory кешом
        $settings = $this->storage->load($scope);

        $this->scopeSettingsCache[$localKey] = $settings;

        return $settings;
    }

    /**
     * Сброс кеша для конкретного scope.
     */
    private function forgetScopeCache(Scope $scope): void
    {
        $scopeKey = $scope->cacheKey();
        $localKey = 'dynamic_config_scope:' . $scopeKey;

        unset($this->scopeSettingsCache[$localKey], $this->localCache[$localKey]);

        // Внешний кеш через Laravel Cache трогаем только если режим = scope
        if ($this->cacheMode === 'scope' && $this->cacheTtl !== null && $this->cacheTtl > 0) {
            $this->cache->forget($localKey);
        }
    }

    /**
     * Предзагрузка всех scope в память (режим cache_mode = preload).
     *
     * Использует ОДИН SELECT по всей таблице dynamic_config_settings.
     */
    private function preloadAllScopes(): void
    {
        // Очистим локальные кеши на случай повторной инициализации
        $this->localCache        = [];
        $this->scopeSettingsCache = [];

        // 1. Читаем ВСЕ действующие настройки за один SELECT
        $rows = DB::table('dynamic_config_settings')
            ->select('scope_type', 'scope_id', 'key', 'value')
            ->whereNull('deleted_at')
            ->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderBy('scope_type')
            ->orderBy('scope_id')
            ->orderBy('key')
            ->get();

        $decoder = config('dynamic_config.decoder');

        foreach ($rows as $row) {
            $type = strtolower((string) $row->scope_type);
            $id   = $row->scope_id !== null ? (int) $row->scope_id : null;

            $scope    = Scope::fromString($type, $id);
            $scopeKey = $scope->cacheKey();
            $cacheKey = 'dynamic_config_scope:' . $scopeKey;

            // Инициализируем структуру для этого scope
            if (!isset($this->localCache[$cacheKey])) {
                $this->localCache[$cacheKey] = [];
            }

            // Декодируем JSON / кастомный формат
            try {
                if ($decoder && is_callable($decoder)) {
                    $decoded = $decoder((string) $row->value);
                } else {
                    $decoded = json_decode((string) $row->value, true, 512, JSON_THROW_ON_ERROR);
                }
            } catch (\Throwable $e) {
                Log::error('DynamicConfig: ошибка декодирования значения при preloadAllScopes', [
                    'scope_type' => $row->scope_type,
                    'scope_id'   => $row->scope_id,
                    'key'        => $row->key,
                    'error'      => $e->getMessage(),
                ]);
                continue;
            }

            // Разворачиваем ключ "group.field" → nested-массив
            Arr::set($this->localCache[$cacheKey], (string) $row->key, $decoded);
        }

        // 2. Заполняем in-memory кеш per request и при необходимости внешний кеш
        foreach ($this->localCache as $cacheKey => $settings) {
            $this->scopeSettingsCache[$cacheKey] = $settings;

            if ($this->cacheMode === 'scope' && $this->cacheTtl !== null && $this->cacheTtl > 0) {
                $this->cache->put($cacheKey, $settings, $this->cacheTtl);
            }
        }

        $this->preloadedAllScopes = true;
    }

    /**
     * Локализация значения.
     *
     * Если значение — translation-массив вида ["ru" => "...", "en" => "..."],
     * выбирает подходящую локаль с fallback.
     */
    private function resolveLocalizedValue(mixed $value, ?string $locale = null): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $locale         = $locale ?? app()->getLocale() ?? $this->defaultLocale;
        $fallbackLocale = $this->fallbackLocale;

        $keys          = array_keys($value);
        $intersect     = array_intersect($keys, $this->locales);
        $isLocaleArray = !empty($intersect);

        if (!$isLocaleArray) {
            return $value;
        }

        if (array_key_exists($locale, $value)) {
            return $value[$locale];
        }

        if ($fallbackLocale !== $locale && array_key_exists($fallbackLocale, $value)) {
            return $value[$fallbackLocale];
        }

        foreach ($this->locales as $lang) {
            if (array_key_exists($lang, $value)) {
                return $value[$lang];
            }
        }

        return reset($value);
    }

    /**
     * Маскирование ключей при логировании (для чувствительных настроек).
     */
    private function maskKey(string $key): string
    {
        foreach ($this->sensitiveKeys as $sensitive) {
            if (str_contains(mb_strtolower($key), mb_strtolower($sensitive))) {
                return '***';
            }
        }

        return $key;
    }

    /**
     * Приведённое чтение: getAs($key, 'int'|'float'|'bool'|'string'|'json')
     */
    public function getAs(
        string $key,
        string $type,
        mixed $default = null,
        ?Scope $scope = null,
        ?string $locale = null
    ): mixed {
        $value = $this->get($key, $default, $scope, $locale);

        return match (strtolower($type)) {
            'int'    => is_numeric($value) ? (int) $value : (int) $default,
            'float'  => is_numeric($value) ? (float) $value : (float) $default,
            'bool'   => (bool) $this->bool($key, (bool) $default, $scope),
            'string' => is_string($value) ? $value : (string) $value,
            'json'   => is_array($value) ? $value : (array) json_decode((string) $value, true),
            default  => $value,
        };
    }

    /**
     * Micro-cache: читать ключ с кешированием на N секунд в памяти процесса.
     *
     * @param string      $key
     * @param int         $ttlSeconds
     * @param mixed       $default
     * @param Scope|null  $scope
     * @param string|null $locale
     *
     * @return mixed
     */
    public function getMicroCached(
        string $key,
        int $ttlSeconds,
        mixed $default = null,
        ?Scope $scope = null,
        ?string $locale = null
    ): mixed {
        $scope ??= $this->scopeResolver->currentScope();
        $cacheKey = $scope->cacheKey() . ':' . $key;

        return $this->getMicroCachedInternal(
            $cacheKey,
            $ttlSeconds,
            fn () => $this->get($key, $default, $scope, $locale)
        );
    }

    /**
     * Внутренний micro-cache по ключу.
     */
    private function getMicroCachedInternal(
        string $cacheKey,
        int $ttlSeconds,
        callable $resolver
    ): mixed {
        $now = time();

        if (isset($this->microCache[$cacheKey])) {
            $item = $this->microCache[$cacheKey];

            if ($item['expires'] >= $now) {
                return $item['value'];
            }

            unset($this->microCache[$cacheKey]);
        }

        $value = $resolver();

        // Можно добавить ограничение размера microCache, если захочешь
        $this->microCache[$cacheKey] = [
            'value'   => $value,
            'expires' => $now + $ttlSeconds,
        ];

        return $value;
    }

    /**
     * Получить несколько ключей за один вызов.
     *
     * @param string[]    $keys
     * @param Scope|null  $scope
     * @param string|null $locale
     *
     * @return array<string,mixed>
     */
    public function getMany(
        array $keys,
        ?Scope $scope = null,
        ?string $locale = null
    ): array {
        $scope ??= $this->scopeResolver->currentScope();

        $result = [];

        foreach ($keys as $key) {
            $key = (string) $key;
            $result[$key] = $this->get($key, null, $scope, $locale);
        }

        return $result;
    }

    /**
     * Поиск ключей по подстроке имени.
     *
     * @return array<string,mixed> key => value
     */
    public function searchKeys(
        string $needle,
        ?Scope $scope = null,
        bool $merged = false
    ): array {
        $scope ??= $this->scopeResolver->currentScope();

        $settings = $this->all($scope, merged: $merged);
        $flat     = Arr::dot($settings);

        $needle = mb_strtolower($needle);
        $result = [];

        foreach ($flat as $key => $value) {
            if (str_contains(mb_strtolower($key), $needle)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Поиск ключей по подстроке в значении (через string cast / json).
     *
     * @return array<string,mixed> key => value
     */
    public function searchByValue(
        string $needle,
        ?Scope $scope = null,
        bool $merged = false
    ): array {
        $scope ??= $this->scopeResolver->currentScope();

        $settings = $this->all($scope, merged: $merged);
        $flat     = Arr::dot($settings);

        $needle = mb_strtolower($needle);
        $result = [];

        foreach ($flat as $key => $value) {
            if (is_scalar($value)) {
                $haystack = mb_strtolower((string) $value);
            } else {
                $haystack = mb_strtolower(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            if (str_contains($haystack, $needle)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
