<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig;

use iEXPackages\DynamicConfig\Access\ConfigAccessControl;
use iEXPackages\DynamicConfig\Contracts\ScopeResolverInterface;
use iEXPackages\DynamicConfig\Contracts\SettingsStorageInterface;
use iEXPackages\DynamicConfig\Schema\ConfigSchemaRegistry;
use iEXPackages\DynamicConfig\Traits\DynamicConfigReadTrait;
use iEXPackages\DynamicConfig\Traits\DynamicConfigSchemaAndLockTrait;
use iEXPackages\DynamicConfig\Traits\DynamicConfigWriteTrait;
use iEXPackages\DynamicConfig\ValueObjects\Scope;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Класс DynamicConfigManager
 *
 * Центральный менеджер настроек:
 *  - знает про scopes (уровни настроек) и цепочку их наследования;
 *  - читает/записывает через SettingsStorageInterface;
 *  - применяет схемы (ConfigSchemaRegistry) и lock’и;
 *  - контролирует доступ (ACL) через ConfigAccessControl;
 *  - использует кеширование по конфигу (cache_mode);
 *  - поддерживает версионирование, soft-delete, lock’и и high-level API.
 */
final class DynamicConfigManager
{
    use DynamicConfigReadTrait;
    use DynamicConfigSchemaAndLockTrait;
    use DynamicConfigWriteTrait;

    /**
     * In-memory кеш настроек по scope внутри одного HTTP-запроса.
     *
     * Ключ: "dynamic_config_scope:{scope->cacheKey()}".
     *
     * @var array<string, array<string,mixed>>
     */
    private array $scopeSettingsCache = [];

    /**
     * Micro-кеш для getMicroCached().
     *
     * Формат:
     *  [
     *      'scopeKey:key' => [
     *          'value'   => mixed,
     *          'expires' => int (timestamp),
     *      ],
     *  ]
     *
     * @var array<string, array{value:mixed,expires:int}>
     */
    private array $microCache = [];

    /**
     * Локальный кеш для режимов cache_mode = 'scope' и 'preload'.
     *
     * Ключ: "dynamic_config_scope:{scope->cacheKey()}".
     *
     * @var array<string, array<string,mixed>>
     */
    private array $localCache = [];

    /**
     * Режим кеширования:
     *
     *  - 'none'    — без внешнего кеша, но с in-memory кешем per-request;
     *  - 'scope'   — кеширование по scope через Laravel Cache;
     *  - 'preload' — при первом обращении загружаем все записи в память одним запросом.
     *
     * @var 'none'|'scope'|'preload'
     */
    private string $cacheMode;

    /**
     * Флаг: были ли уже предзагружены все scope (для cache_mode = 'preload').
     */
    private bool $preloadedAllScopes = false;

    /**
     * Хранилище настроек.
     */
    private SettingsStorageInterface $storage;

    /**
     * Резолвер текущего scope (определяет текущий контекст).
     */
    private ScopeResolverInterface $scopeResolver;

    /**
     * Кеш-репозиторий Laravel для cache_mode = 'scope'.
     */
    private CacheRepository $cache;

    /**
     * TTL внешнего кеша (секунды или минуты — как настроено).
     *
     * @var int|null
     */
    private ?int $cacheTtl;

    /**
     * Список поддерживаемых локалей.
     *
     * @var string[]
     */
    private array $locales;

    /**
     * Локаль по умолчанию (например, 'ru').
     */
    private string $defaultLocale;

    /**
     * Локаль-фолбэк (например, 'en').
     */
    private string $fallbackLocale;

    /**
     * Фрагменты чувствительных ключей, которые нужно маскировать в логах.
     *
     * @var string[]
     */
    private array $sensitiveKeys;

    /**
     * Реестр схем (может быть null, если схемы не используются).
     */
    private ?ConfigSchemaRegistry $schema;

    /**
     * Контроль доступа к настройкам (может быть null, если ACL не используется).
     */
    private ?ConfigAccessControl $access;

    /**
     * Принудительно ли проверять права на запись.
     */
    private bool $enforceWriteAccess;

    /**
     * Принудительно ли проверять права на чтение.
     */
    private bool $enforceReadAccess;

    /**
     * DynamicConfigManager constructor.
     *
     * @param SettingsStorageInterface  $storage       Хранилище настроек.
     * @param ScopeResolverInterface    $scopeResolver Резолвер текущего scope.
     * @param CacheFactory              $cacheFactory  Фабрика кеша Laravel.
     * @param ConfigSchemaRegistry|null $schema        Реестр схем (может быть null).
     * @param ConfigAccessControl|null  $access        ACL-контроллер (может быть null).
     */
    public function __construct(
        SettingsStorageInterface $storage,
        ScopeResolverInterface $scopeResolver,
        CacheFactory $cacheFactory,
        ?ConfigSchemaRegistry $schema = null,
        ?ConfigAccessControl $access = null
    ) {
        $this->storage       = $storage;
        $this->scopeResolver = $scopeResolver;
        $this->schema        = $schema;

        $this->cacheMode = (string) config('dynamic_config.cache_mode', 'scope');

        $cacheStore = config('dynamic_config.cache_store');
        $this->cache = $cacheStore
            ? $cacheFactory->store($cacheStore)
            : $cacheFactory->store();

        $this->cacheTtl       = config('dynamic_config.cache_ttl');
        $this->locales        = config('dynamic_config.locales', ['ru', 'en']);
        $this->defaultLocale  = config('dynamic_config.default_locale', 'ru');
        $this->fallbackLocale = config('dynamic_config.fallback_locale', 'en');
        $this->sensitiveKeys  = config('dynamic_config.sensitive_keys', []);

        $this->access = $access;

        $this->enforceWriteAccess = (bool) config('dynamic_config.access_control.enforce_writes', true);
        $this->enforceReadAccess  = (bool) config('dynamic_config.access_control.enforce_reads', false);
    }

    /**
     * Прочитать «сырое» значение настройки без применения локализации.
     *
     * В отличие от get():
     *  - НЕ вызывает resolveLocalizedValue();
     *  - возвращает массив для type=translation (например, ["ru" => "...", "en" => "..."]).
     *
     * @param string      $key      Ключ настройки (dot-notation).
     * @param mixed       $default  Значение по умолчанию.
     * @param Scope|null  $scope    Scope (по умолчанию текущий).
     * @param bool        $merged   true — использовать наследование scope (global → ... → current),
     *                              false — только конкретный scope.
     *
     * @return mixed
     */
    public function getRaw(
        string $key,
        mixed $default = null,
        ?Scope $scope = null,
        bool $merged = true
    ): mixed {
        $scope ??= $this->scopeResolver->currentScope();

        try {
            $settings = $merged
                ? $this->getMergedSettingsForScope($scope)
                : $this->getSettingsForExactScope($scope);

            return Arr::get($settings, $key, $default);
        } catch (\Throwable $e) {
            $maskedKey = $this->maskKey($key);
            Log::error('DynamicConfig: ошибка в getRaw()', [
                'key'     => $maskedKey,
                'message' => $e->getMessage(),
            ]);

            return $default;
        }
    }

    /**
     * Удобный alias: получить ScopedDynamicConfig для конкретного scope.
     *
     * Пример:
     *   $userConfig = $manager->forScope('user', $userId);
     *   $theme      = $userConfig->get('ui.theme');
     */
    public function forScope(string $type, ?int $id = null): ScopedDynamicConfig
    {
        $scope = Scope::fromString($type, $id);

        return new ScopedDynamicConfig($this, $scope);
    }
}
