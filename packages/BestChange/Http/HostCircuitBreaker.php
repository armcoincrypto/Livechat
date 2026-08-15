<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Http;

use Illuminate\Support\Facades\Cache;

/**
 * HostCircuitBreaker
 *
 * Circuit-breaker по зеркалам BestChange.
 * Если зеркало падает несколько раз — временно не используем.
 */
final class HostCircuitBreaker
{
    public function __construct(
        private readonly int $failThreshold = 3,
        private readonly int $ttlSeconds = 120,
        private readonly string $prefix = 'bestchange:host_fail:',
    ) {}

    public function isBlocked(string $host): bool
    {
        return (int) Cache::get($this->prefix . $host, 0) >= $this->failThreshold;
    }

    public function markSuccess(string $host): void
    {
        Cache::forget($this->prefix . $host);
    }

    public function markFail(string $host): void
    {
        $key = $this->prefix . $host;
        $fails = (int) Cache::get($key, 0);
        Cache::put($key, $fails + 1, $this->ttlSeconds);
    }
}
