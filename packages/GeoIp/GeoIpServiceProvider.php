<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp;

use iEXPackages\GeoIp\AntiFraud\PolicyEngine;
use iEXPackages\GeoIp\AntiFraud\Rules\AllowCountriesRule;
use iEXPackages\GeoIp\AntiFraud\Rules\DenyCountriesRule;
use iEXPackages\GeoIp\AntiFraud\Rules\IpQualityRule;
use iEXPackages\GeoIp\AntiFraud\Rules\OnlyEuRule;
use iEXPackages\GeoIp\AntiFraud\Rules\ReadonlyCacheRule;
use iEXPackages\GeoIp\AntiFraud\Rules\RegisteredMismatchRule;
use iEXPackages\GeoIp\Console\GeoIpBenchCommand;
use iEXPackages\GeoIp\Console\GeoIpInfoCommand;
use iEXPackages\GeoIp\Console\GeoIpTestCommand;
use iEXPackages\GeoIp\Console\GeoIpUpdateCommand;
use iEXPackages\GeoIp\Console\GeoIpWarmupCommand;
use iEXPackages\GeoIp\Contracts\DynamicConfigReaderInterface;
use iEXPackages\GeoIp\Contracts\GeoIpDriverInterface;
use iEXPackages\GeoIp\Contracts\IpResolverInterface;
use iEXPackages\GeoIp\Contracts\MetricsInterface;
use iEXPackages\GeoIp\Contracts\ToggleSourceInterface;
use iEXPackages\GeoIp\Drivers\MaxMindDatabaseDriver;
use iEXPackages\GeoIp\Metrics\NullMetrics;
use iEXPackages\GeoIp\Support\CachedToggleSource;
use iEXPackages\GeoIp\Support\CircuitBreaker;
use iEXPackages\GeoIp\Support\DynamicConfigReader;
use iEXPackages\GeoIp\Support\DynamicConfigToggleSource;
use iEXPackages\GeoIp\Support\FeatureToggles;
use iEXPackages\GeoIp\Support\GeoIpPipeline;
use iEXPackages\GeoIp\Support\IpResolver;
use iEXPackages\GeoIp\Support\LogLimiter;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\ServiceProvider;

final class GeoIpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MetricsInterface::class, fn () => new NullMetrics());

        // ------------------------------------------------------------
        // DynamicConfig reader (адаптер)
        // ------------------------------------------------------------
        $this->app->singleton(DynamicConfigReaderInterface::class, fn () => new DynamicConfigReader());

        // ------------------------------------------------------------
        // Toggle source (DynamicConfig -> cached)
        // ------------------------------------------------------------
        $this->app->singleton(ToggleSourceInterface::class, function ($app) {
            $cfg = (array) config('geoip', []);
            $dc = (array)($cfg['dynamic_config'] ?? []);

            $enabled = (bool)($dc['enabled'] ?? true);
            $prefix = (string)($dc['prefix'] ?? 'geoip.toggles.');
            $ttl = (int)($dc['ttl'] ?? 30);
            $cachePrefix = (string)($dc['cache_prefix'] ?? 'geoip:dyn_toggle:');
            $allowedKeys = (array)($dc['keys'] ?? []);

            /** @var CacheFactory|null $cacheFactory */
            $cacheFactory = $app->bound(CacheFactory::class) ? $app->make(CacheFactory::class) : null;

            $cacheRepo = null;
            if ($cacheFactory) {
                $cacheCfg = (array)($cfg['cache'] ?? []);
                $store = $cacheCfg['store'] ?? null;
                $cacheRepo = $store ? $cacheFactory->store($store) : $cacheFactory->store();
            }

            $base = new DynamicConfigToggleSource(
                reader: $app->make(DynamicConfigReaderInterface::class),
                prefix: $prefix,
                enabled: $enabled,
                allowedKeys: $allowedKeys,
            );

            return new CachedToggleSource(
                inner: $base,
                cache: $cacheRepo,
                ttlSeconds: $ttl,
                cachePrefix: $cachePrefix
            );
        });

        $this->app->singleton(FeatureToggles::class, function ($app) {
            $cfg = (array) config('geoip', []);
            return new FeatureToggles(
                source: $app->make(ToggleSourceInterface::class),
                defaults: (array)($cfg['toggles'] ?? [])
            );
        });

        // ------------------------------------------------------------
        // Pipeline
        // ------------------------------------------------------------
        $this->app->singleton(GeoIpPipeline::class, function ($app) {
            $cfg = (array) config('geoip', []);
            $processors = (array)($cfg['pipeline'] ?? []);
            return new GeoIpPipeline($app, $processors);
        });

        // ------------------------------------------------------------
        // IP resolver
        // ------------------------------------------------------------
        $this->app->singleton(IpResolverInterface::class, function ($app) {
            $cfg = (array) config('geoip', []);
            $request = $app->bound('request') ? $app->make('request') : null;

            return new IpResolver(
                request: $request,
                source: (string)($cfg['ip_source'] ?? 'request'),
            );
        });

        // ------------------------------------------------------------
        // Driver
        // ------------------------------------------------------------
        $this->app->singleton(GeoIpDriverInterface::class, function () {
            $cfg = (array) config('geoip', []);
            $db = (array)($cfg['databases'] ?? []);

            return new MaxMindDatabaseDriver([
                'city' => (string)($db['city'] ?? ''),
                'country' => (string)($db['country'] ?? ''),
                'asn' => (string)($db['asn'] ?? ''),
            ]);
        });

        // ------------------------------------------------------------
        // CircuitBreaker / LogLimiter (используют тот же cache store)
        // ------------------------------------------------------------
        $this->app->singleton(CircuitBreaker::class, function ($app) {
            $cfg = (array) config('geoip', []);
            $cb = (array)($cfg['circuit_breaker'] ?? []);
            $cacheCfg = (array)($cfg['cache'] ?? []);

            /** @var CacheFactory $cacheFactory */
            $cacheFactory = $app->make(CacheFactory::class);
            $store = $cacheCfg['store'] ?? null;
            $repo = $store ? $cacheFactory->store($store) : $cacheFactory->store();

            return new CircuitBreaker(
                cache: $repo,
                prefix: (string)($cb['prefix'] ?? 'geoip:cb:'),
                threshold: (int)($cb['threshold'] ?? 5),
                windowSeconds: (int)($cb['window_seconds'] ?? 60),
                cooldownSeconds: (int)($cb['cooldown_seconds'] ?? 120),
                enabled: (bool)($cb['enabled'] ?? true),
            );
        });

        $this->app->singleton(LogLimiter::class, function ($app) {
            $cfg = (array) config('geoip', []);
            $ll = (array)($cfg['log_limiter'] ?? []);
            $cacheCfg = (array)($cfg['cache'] ?? []);

            /** @var CacheFactory $cacheFactory */
            $cacheFactory = $app->make(CacheFactory::class);
            $store = $cacheCfg['store'] ?? null;
            $repo = $store ? $cacheFactory->store($store) : $cacheFactory->store();

            return new LogLimiter(
                cache: $repo,
                prefix: (string)($ll['prefix'] ?? 'geoip:log:'),
                ttlSeconds: (int)($ll['ttl'] ?? 300),
                enabled: (bool)($ll['enabled'] ?? true),
            );
        });

        // ------------------------------------------------------------
        // Main GeoIp service
        // ------------------------------------------------------------
        $this->app->singleton(GeoIp::class, function ($app) {
            $cfg = (array) config('geoip', []);

            /** @var CacheFactory|null $cacheFactory */
            $cacheFactory = $app->bound(CacheFactory::class) ? $app->make(CacheFactory::class) : null;

            return new GeoIp(
                driver: $app->make(GeoIpDriverInterface::class),
                ipResolver: $app->make(IpResolverInterface::class),
                cacheFactory: $cacheFactory,
                metrics: $app->make(MetricsInterface::class),
                config: $cfg,
                circuitBreaker: $app->make(CircuitBreaker::class),
                logLimiter: $app->make(LogLimiter::class),
                toggles: $app->make(FeatureToggles::class),
                pipeline: $app->make(GeoIpPipeline::class),
            );
        });

        $this->app->singleton(PolicyEngine::class, function ($app) {
            return new PolicyEngine([
                // порядок важен:
                // 1) если страна неизвестна — решаем сразу
                $app->make(IpQualityRule::class),

                // 2) аварийный режим может смягчить решение
                $app->make(ReadonlyCacheRule::class),

                // 3) запреты имеют приоритет
                $app->make(DenyCountriesRule::class),

                // 4) allow-list (если задан) — только разрешённые
                $app->make(AllowCountriesRule::class),

                // 5) ограничения "только ЕС"
                $app->make(OnlyEuRule::class),

                // 6) mismatch registered_country
                $app->make(RegisteredMismatchRule::class),
            ]);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GeoIpTestCommand::class,
                GeoIpInfoCommand::class,
                GeoIpUpdateCommand::class,
                GeoIpBenchCommand::class,
                GeoIpWarmupCommand::class,
            ]);
        }
    }
}
