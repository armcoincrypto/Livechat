<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Support;

use Illuminate\Contracts\Cache\Repository;

/**
 * Circuit breaker:
 * - если ошибок подряд много => открываем на cooldown => fail-open.
 */
final class CircuitBreaker
{
    public function __construct(
        private readonly Repository $cache,
        private readonly string $prefix,
        private readonly int $threshold,
        private readonly int $windowSeconds,
        private readonly int $cooldownSeconds,
        private readonly bool $enabled,
    ) {}

    public function isOpen(): bool
    {
        if (!$this->enabled) return false;
        return (bool) $this->cache->get($this->prefix.'open', false);
    }

    public function reportSuccess(): void
    {
        if (!$this->enabled) return;
        $this->cache->forget($this->prefix.'err_count');
    }

    public function reportFailure(): void
    {
        if (!$this->enabled) return;

        $key = $this->prefix.'err_count';
        $count = (int) $this->cache->get($key, 0);
        $count++;

        $this->cache->put($key, $count, $this->windowSeconds);

        if ($count >= $this->threshold) {
            $this->cache->put($this->prefix.'open', true, $this->cooldownSeconds);
        }
    }
}
