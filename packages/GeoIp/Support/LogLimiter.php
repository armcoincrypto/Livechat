<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Support;

use Illuminate\Contracts\Cache\Repository;

/**
 * Ограничение логов: не чаще 1 раза в ttl на ключ.
 */
final class LogLimiter
{
    public function __construct(
        private readonly Repository $cache,
        private readonly string $prefix,
        private readonly int $ttlSeconds,
        private readonly bool $enabled,
    ) {}

    public function allow(string $key): bool
    {
        if (!$this->enabled) return true;

        $k = $this->prefix.$key;
        if ($this->cache->get($k)) {
            return false;
        }

        $this->cache->put($k, true, $this->ttlSeconds);
        return true;
    }
}
