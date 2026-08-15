<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Http;

use Illuminate\Support\Facades\Cache;

/**
 * RequestRateLimiter
 *
 * Глобальное ограничение запросов (BestChange рекомендует 30 rps).
 */
final class RequestRateLimiter
{
    public function __construct(
        private readonly string $bucketKey,
        private readonly int $limitRps,
        private readonly int $sleepUs,
    ) {}

    public function throttle(): void
    {
        if ($this->limitRps <= 0) return;

        $windowKey = $this->bucketKey . ':' . now()->format('YmdHis');
        $count = Cache::increment($windowKey);

        if ($count === 1) {
            Cache::put($windowKey, 1, 2);
        }

        if ($count > $this->limitRps) {
            usleep($this->sleepUs);
        }
    }
}
