<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Services;

use iEXPackages\BestChange\Contracts\BestChangeHttpClientInterface;
use Illuminate\Support\Facades\Cache;

/**
 * PresencesService
 *
 * Быстрая диагностика рынка через /presences.
 *
 * Важно:
 * - presence НЕ должен ломать основной процесс.
 * - Любая ошибка сети/ключа => просто возвращаем null.
 */
final class PresencesService
{
    public function __construct(
        private readonly BestChangeHttpClientInterface $httpClient,
    ) {}

    /**
     * @return array{pair?:string,best?:string|float,count?:int}|null
     */
    public function get(string $pairKey, int $ttlSeconds = 60): ?array
    {
        $pairKey = trim($pairKey);
        if ($pairKey === '') {
            return null;
        }

        $ttlSeconds = max(5, min(600, $ttlSeconds));
        $cacheKey = "bestchange:presence:{$pairKey}";

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $presence = $this->httpClient->fetchPresence($pairKey);
            if (is_array($presence)) {
                Cache::put($cacheKey, $presence, $ttlSeconds);
                return $presence;
            }
        } catch (\Throwable) {
            // presence — опционально, ошибки гасим
        }

        return null;
    }
}
