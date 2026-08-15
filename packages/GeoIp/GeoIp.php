<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp;

use iEXPackages\GeoIp\Contracts\GeoIpDriverInterface;
use iEXPackages\GeoIp\Contracts\IpResolverInterface;
use iEXPackages\GeoIp\Contracts\MetricsInterface;
use iEXPackages\GeoIp\DTO\GeoIpContext;
use iEXPackages\GeoIp\DTO\Location;
use iEXPackages\GeoIp\Support\CacheKey;
use iEXPackages\GeoIp\Support\CircuitBreaker;
use iEXPackages\GeoIp\Support\FeatureToggles;
use iEXPackages\GeoIp\Support\GeoIpPipeline;
use iEXPackages\GeoIp\Support\Ip;
use iEXPackages\GeoIp\Support\LocalCache;
use iEXPackages\GeoIp\Support\LogLimiter;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;

/**
 * GeoIp — стабильный и производительный сервис GeoIP.
 *
 * Правила:
 * 1) enabled=false → полностью отключено (без кеша/драйвера/pipeline)
 * 2) readonly_cache=true → только чтение L2 кеша, без записи и без драйвера
 */
final class GeoIp
{
    public function __construct(
        private readonly GeoIpDriverInterface $driver,
        private readonly IpResolverInterface $ipResolver,
        private readonly ?CacheFactory $cacheFactory,
        private readonly MetricsInterface $metrics,
        private readonly array $config,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly LogLimiter $logLimiter,
        private readonly FeatureToggles $toggles,
        private readonly GeoIpPipeline $pipeline,
    ) {}

    public function locate(?string $ip = null, ?string $locale = null): Location
    {
        return $this->run('locate', $ip, $locale, fn (string $x, string $loc) => $this->driver->locate($x, $loc));
    }

    public function country(?string $ip = null, ?string $locale = null): Location
    {
        return $this->run('country', $ip, $locale, fn (string $x, string $loc) => $this->driver->country($x, $loc));
    }

    public function asn(?string $ip = null, ?string $locale = null): Location
    {
        return $this->run('asn', $ip, $locale, fn (string $x, string $loc) => $this->driver->asn($x, $loc));
    }

    /**
     * Bulk locate (для аналитики).
     *
     * @param string[] $ips
     * @return array<string,Location>
     */
    public function bulkLocate(array $ips, ?string $locale = null): array
    {
        return $this->bulk('locate', $ips, $locale);
    }

    /**
     * @param string[] $ips
     * @return array<string,Location>
     */
    public function bulkCountry(array $ips, ?string $locale = null): array
    {
        return $this->bulk('country', $ips, $locale);
    }

    /**
     * @param string[] $ips
     * @return array<string,Location>
     */
    public function bulkAsn(array $ips, ?string $locale = null): array
    {
        return $this->bulk('asn', $ips, $locale);
    }

    /**
     * @param callable(string,string):Location $resolver
     */
    private function run(string $type, ?string $ip, ?string $locale, callable $resolver): Location
    {
        // 1) Полное отключение — без кеша/драйвера/pipeline
        if (!$this->toggles->enabled('enabled', true)) {
            return Location::empty('0.0.0.0');
        }

        $t0 = microtime(true);

        $debugCfg = (array)($this->config['debug'] ?? []);
        $debugEnabled = (bool)($debugCfg['enabled'] ?? false);

        $resolvedIp = $this->safeResolveIp($ip);
        if ($resolvedIp === null) {
            $ctx = new GeoIpContext(
                type: $type,
                ip: '0.0.0.0',
                locale: $this->resolveLocale($locale),
                source: 'skip',
                debugEnabled: $debugEnabled,
            );
            $ctx->timingMs = (microtime(true) - $t0) * 1000;

            $this->metrics->inc('geoip_skip_total', ['type' => $type]);
            return $this->finalize(Location::empty('0.0.0.0')->toArray(), $ctx);
        }

        $loc = $this->resolveLocale($locale);

        // Circuit breaker
        if ($this->circuitBreaker->isOpen()) {
            $ctx = new GeoIpContext($type, $resolvedIp, $loc, 'circuit_open', $debugEnabled);
            $ctx->debug['reason'] = 'circuit breaker open';
            $ctx->timingMs = (microtime(true) - $t0) * 1000;

            $this->metrics->inc('geoip_circuit_open_total', ['type' => $type]);
            return $this->finalize(Location::empty($resolvedIp)->toArray(), $ctx);
        }

        // Config/cache policy
        $policy = (string)($this->config['cache_policy'] ?? 'public_only');
        $shouldCache = $this->shouldCache($resolvedIp, $policy);

        // L1 local cache (config-only)
        $localCfg = (array)($this->config['local_cache'] ?? []);
        $localEnabled = (bool)($localCfg['enabled'] ?? true);
        $localMax = (int)($localCfg['max_items'] ?? 5000);

        // L2 cache (toggle + config)
        $cacheCfg = (array)($this->config['cache'] ?? []);
        $cacheEnabledCfg = (bool)($cacheCfg['enabled'] ?? true);
        $cacheEnabled = $cacheEnabledCfg && $this->toggles->enabled('cache_enabled', true);

        $prefix = (string)($cacheCfg['prefix'] ?? 'geoip:');
        $key = CacheKey::data($prefix, $type, $resolvedIp, $loc);

        // L1 hit (разрешено только при enabled=true — а мы уже тут)
        if ($localEnabled) {
            $hit = LocalCache::get($key);
            if (is_array($hit)) {
                $ctx = new GeoIpContext($type, $resolvedIp, $loc, 'local_cache', $debugEnabled);
                $ctx->timingMs = (microtime(true) - $t0) * 1000;

                $this->metrics->inc('geoip_local_cache_hit_total', ['type' => $type]);
                return $this->finalize($hit, $ctx);
            }
        }

        // 2) readonly_cache — только чтение L2 кеша, без записи/драйвера
        if ($this->toggles->enabled('readonly_cache', false)) {
            if ($cacheEnabled && $shouldCache && $this->cacheFactory) {
                $repo = $this->cacheRepo();
                $cached = $repo->get($key);

                if (is_array($cached)) {
                    $ctx = new GeoIpContext($type, $resolvedIp, $loc, 'cache_hit', $debugEnabled);
                    $ctx->debug['readonly_cache'] = true;
                    $ctx->timingMs = (microtime(true) - $t0) * 1000;

                    if ($localEnabled) {
                        LocalCache::put($key, $cached, $localMax);
                    }

                    return $this->finalize($cached, $ctx);
                }
            }

            $ctx = new GeoIpContext($type, $resolvedIp, $loc, 'readonly_cache', $debugEnabled);
            $ctx->debug['readonly_cache'] = true;
            $ctx->timingMs = (microtime(true) - $t0) * 1000;

            return $this->finalize(Location::empty($resolvedIp)->toArray(), $ctx);
        }

        // bypass (нет L2 кеша или не нужно кешировать)
        if (!$cacheEnabled || !$shouldCache || !$this->cacheFactory) {
            $location = $this->safeCall($type, $resolvedIp, fn () => $resolver($resolvedIp, $loc));
            $payload = $location->toArray();

            $ctx = new GeoIpContext($type, $resolvedIp, $loc, 'bypass', $debugEnabled);
            $ctx->timingMs = (microtime(true) - $t0) * 1000;

            $this->metrics->inc('geoip_cache_bypass_total', ['type' => $type]);
            $this->metrics->timing('geoip_lookup_ms', $ctx->timingMs, ['type' => $type]);

            return $this->finalize($payload, $ctx);
        }

        $repo = $this->cacheRepo();

        // L2 hit
        $cached = $repo->get($key);
        if (is_array($cached)) {
            $ctx = new GeoIpContext($type, $resolvedIp, $loc, 'cache_hit', $debugEnabled);
            $ctx->timingMs = (microtime(true) - $t0) * 1000;

            $this->metrics->inc('geoip_cache_hit_total', ['type' => $type]);
            $this->metrics->timing('geoip_lookup_ms', $ctx->timingMs, ['type' => $type]);

            if ($localEnabled) {
                LocalCache::put($key, $cached, $localMax);
            }

            return $this->finalize($cached, $ctx);
        }

        $this->metrics->inc('geoip_cache_miss_total', ['type' => $type]);

        $lockSeconds = (int)($cacheCfg['lock_seconds'] ?? 3);
        $lockKey = CacheKey::lock($key);

        // compute + write cache (тут enabled=true и readonly_cache=false гарантированно)
        $arr = $this->computeWithLock(
            repo: $repo,
            key: $key,
            lockKey: $lockKey,
            lockSeconds: $lockSeconds,
            compute: function () use ($type, $resolvedIp, $loc, $resolver, $repo, $key, $cacheCfg) {
                $cached2 = $repo->get($key);
                if (is_array($cached2)) {
                    return $cached2;
                }

                $location = $this->safeCall($type, $resolvedIp, fn () => $resolver($resolvedIp, $loc));

                $ttlHit = (int)($cacheCfg['ttl_hit'] ?? 86400);
                $ttlMiss = (int)($cacheCfg['ttl_miss'] ?? 3600);

                $isMiss = ($location->countryIso() === null && $location->city() === null);

                $data = $location->toArray();

                if ($isMiss) {
                    return $data;
                }

                $ttlHit = (int)($cacheCfg['ttl_hit'] ?? 86400);
                $repo->put($key, $data, $ttlHit);

                return $data;
            }
        );

        // L1: можно положить результат только если это массив
        if ($localEnabled && is_array($arr)) {
            LocalCache::put($key, $arr, $localMax);
        }

        $ctx = new GeoIpContext($type, $resolvedIp, $loc, 'computed', $debugEnabled);
        $ctx->timingMs = (microtime(true) - $t0) * 1000;

        $this->metrics->timing('geoip_lookup_ms', $ctx->timingMs, ['type' => $type]);

        return $this->finalize(is_array($arr) ? $arr : Location::empty($resolvedIp)->toArray(), $ctx);
    }

    private function finalize(array $payload, GeoIpContext $ctx): Location
    {
        if ($this->toggles->enabled('pipeline_enabled', true)) {
            $payload = $this->pipeline->run($payload, $ctx);
        }

        return Location::fromArray($payload);
    }

    /**
     * @param string[] $ips
     * @return array<string,Location>
     */
    private function bulk(string $type, array $ips, ?string $locale): array
    {
        // Полное отключение — bulk тоже не работает
        if (!$this->toggles->enabled('enabled', true)) {
            return [];
        }

        $debugCfg = (array)($this->config['debug'] ?? []);
        $debugEnabled = (bool)($debugCfg['enabled'] ?? false);

        $loc = $this->resolveLocale($locale);

        // uniq
        $unique = [];
        foreach ($ips as $ip) {
            $ip = trim((string)$ip);
            if ($ip !== '') $unique[$ip] = true;
        }
        $list = array_keys($unique);
        if ($list === []) return [];

        // readonly_cache: только читаем L2
        if ($this->toggles->enabled('readonly_cache', false)) {
            $out = [];

            $cacheCfg = (array)($this->config['cache'] ?? []);
            $cacheEnabledCfg = (bool)($cacheCfg['enabled'] ?? true);
            $cacheEnabled = $cacheEnabledCfg && $this->toggles->enabled('cache_enabled', true) && $this->cacheFactory !== null;

            if (!$cacheEnabled) {
                foreach ($list as $ip) {
                    $out[$ip] = Location::empty($ip);
                }
                return $out;
            }

            $repo = $this->cacheRepo();
            $prefix = (string)($cacheCfg['prefix'] ?? 'geoip:');

            $keys = [];
            $ipByKey = [];
            foreach ($list as $ip) {
                $k = CacheKey::data($prefix, $type, $ip, $loc);
                $keys[] = $k;
                $ipByKey[$k] = $ip;
            }

            $cachedMany = method_exists($repo, 'many') ? $repo->many($keys) : [];
            if (is_array($cachedMany)) {
                foreach ($cachedMany as $k => $v) {
                    if (is_array($v) && isset($ipByKey[$k])) {
                        $ctx = new GeoIpContext($type, $ipByKey[$k], $loc, 'cache_hit', $debugEnabled);
                        $ctx->debug['readonly_cache'] = true;
                        $out[$ipByKey[$k]] = $this->finalize($v, $ctx);
                    }
                }
            }

            foreach ($list as $ip) {
                if (!isset($out[$ip])) {
                    $ctx = new GeoIpContext($type, $ip, $loc, 'readonly_cache', $debugEnabled);
                    $ctx->debug['readonly_cache'] = true;
                    $out[$ip] = $this->finalize(Location::empty($ip)->toArray(), $ctx);
                }
            }

            return $out;
        }

        // обычный bulk: читаем L2 пачкой, miss считаем через run()
        $out = [];
        $repo = null;

        $cacheCfg = (array)($this->config['cache'] ?? []);
        $cacheEnabledCfg = (bool)($cacheCfg['enabled'] ?? true);
        $cacheEnabled = $cacheEnabledCfg && $this->toggles->enabled('cache_enabled', true) && $this->cacheFactory !== null;

        if ($cacheEnabled) {
            $repo = $this->cacheRepo();
            $prefix = (string)($cacheCfg['prefix'] ?? 'geoip:');

            $keys = [];
            $ipByKey = [];
            foreach ($list as $ip) {
                $k = CacheKey::data($prefix, $type, $ip, $loc);
                $keys[] = $k;
                $ipByKey[$k] = $ip;
            }

            $cachedMany = method_exists($repo, 'many') ? $repo->many($keys) : [];
            if (is_array($cachedMany)) {
                foreach ($cachedMany as $k => $v) {
                    if (is_array($v) && isset($ipByKey[$k])) {
                        $ctx = new GeoIpContext($type, $ipByKey[$k], $loc, 'cache_hit', $debugEnabled);
                        $out[$ipByKey[$k]] = $this->finalize($v, $ctx);
                    }
                }
            }
        }

        // miss -> считаем обычным путём (там правильная логика cache/pipeline)
        foreach ($list as $ip) {
            if (isset($out[$ip])) continue;

            $out[$ip] = match ($type) {
                'country' => $this->country($ip, $loc),
                'asn' => $this->asn($ip, $loc),
                default => $this->locate($ip, $loc),
            };
        }

        return $out;
    }

    private function shouldCache(string $ip, string $policy): bool
    {
        return match ($policy) {
            'none' => false,
            'all' => true,
            default => Ip::isPublic($ip),
        };
    }

    private function safeResolveIp(?string $ip): ?string
    {
        try {
            $resolved = $this->ipResolver->resolve($ip);
        } catch (\Throwable) {
            return null;
        }

        if (($this->config['skip_private_ips'] ?? true) && Ip::isPrivate($resolved)) return null;
        if (($this->config['skip_reserved_ips'] ?? true) && Ip::isReserved($resolved)) return null;

        return $resolved;
    }

    private function resolveLocale(?string $locale): string
    {
        $cfgLocale = trim((string)($this->config['locale'] ?? 'auto'));
        $fallback = trim((string)($this->config['locale_fallback'] ?? 'en'));

        $passed = trim((string)$locale);
        if ($passed !== '') return $passed;

        if ($cfgLocale !== '' && $cfgLocale !== 'auto') return $cfgLocale;

        try {
            $appLocale = function_exists('app') ? trim((string)app()->getLocale()) : '';
            return $appLocale !== '' ? $appLocale : ($fallback !== '' ? $fallback : 'en');
        } catch (\Throwable) {
            return $fallback !== '' ? $fallback : 'en';
        }
    }

    private function cacheRepo(): CacheRepository
    {
        $cacheCfg = (array)($this->config['cache'] ?? []);
        $storeName = $cacheCfg['store'] ?? null;

        return $storeName ? $this->cacheFactory->store($storeName) : $this->cacheFactory->store();
    }

    /**
     * @param callable():array $compute
     */
    private function computeWithLock(CacheRepository $repo, string $key, string $lockKey, int $lockSeconds, callable $compute): array
    {
        if (method_exists($repo, 'lock')) {
            $lock = $repo->lock($lockKey, $lockSeconds);

            if ($lock->get()) {
                try {
                    return $compute();
                } finally {
                    try { $lock->release(); } catch (\Throwable) {}
                }
            }

            usleep(150_000);
            $cached = $repo->get($key);
            if (is_array($cached)) return $cached;
        }

        return $compute();
    }

    /**
     * @param callable():Location $cb
     */
    private function safeCall(string $type, string $ip, callable $cb): Location
    {
        try {
            $res = $cb();
            $this->circuitBreaker->reportSuccess();
            $this->metrics->inc('geoip_success_total', ['type' => $type]);
            return $res;
        } catch (\Throwable $e) {
            $this->circuitBreaker->reportFailure();
            $this->metrics->inc('geoip_error_total', ['type' => $type, 'ex' => class_basename($e)]);

            $mode = (string)($this->config['fail_mode'] ?? 'null_location');

            if ($mode === 'throw') {
                throw $e;
            }

            if ($mode === 'report') {
                report($e);
                return Location::empty($ip);
            }

            $logKey = $type.':'.class_basename($e);
            if ($this->logLimiter->allow($logKey)) {
                Log::warning('GeoIP error (return empty location)', [
                    'type' => $type,
                    'ip' => $ip,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]);
            }

            return Location::empty($ip);
        }
    }
}
