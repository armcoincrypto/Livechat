<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Support;

use iEXPackages\GeoIp\Contracts\ToggleSourceInterface;
use Illuminate\Contracts\Cache\Repository;

/**
 * Кеширует toggles в CacheRepository, но НЕ допускает "залипания":
 * - всегда читает актуальное значение из источника (DynamicConfig)
 * - если значение изменилось — обновляет кеш
 *
 * Это гарантирует, что переключатели применяются сразу.
 */
final class CachedToggleSource implements ToggleSourceInterface
{
    public function __construct(
        private readonly ToggleSourceInterface $inner,
        private readonly ?Repository $cache,
        private readonly int $ttlSeconds,
        private readonly string $cachePrefix,
    ) {}

    public function get(string $key): mixed
    {
        // 1) Всегда читаем актуальное значение из источника (DynamicConfig)
        $fresh = $this->inner->get($key);

        // Если кеша нет — просто возвращаем
        if (!$this->cache) {
            return $fresh;
        }

        $ck = $this->cachePrefix.$key;

        // 2) Если в кеше есть значение — сравним
        if ($this->cache->has($ck)) {
            $cached = $this->cache->get($ck);

            // Если совпадает — возвращаем кеш (быстро)
            if ($cached === $fresh) {
                return $cached;
            }
        }

        // 3) Если отличается или кеша нет — обновляем кеш и возвращаем актуальное
        $this->cache->put($ck, $fresh, $this->ttlSeconds);

        return $fresh;
    }
}
