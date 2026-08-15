<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Services;

use Illuminate\Support\Facades\Cache;

final class GatewayOptionsCache
{
    /**
     * TTL по умолчанию (сек).
     * Можно переопределять через config('payments.options_cache_ttl').
     */
    public function ttlSeconds(): int
    {
        return (int) config('payments.options_cache_ttl', 600); // 10 минут
    }

    /**
     * Ключ кеша.
     */
    public function key(string $alias, string $group, string $method, string $configHash, string $paramsHash): string
    {
        return "payments:options:{$alias}:{$group}:{$method}:{$configHash}:{$paramsHash}";
    }

    /**
     * Взять из кеша.
     *
     * @return array<string,string>|null
     */
    public function get(string $key): ?array
    {
        $v = Cache::get($key);

        return is_array($v) ? $v : null;
    }

    /**
     * Положить в кеш.
     *
     * @param array<string,string> $value
     */
    public function put(string $key, array $value, ?int $ttlSeconds = null): void
    {
        Cache::put($key, $value, now()->addSeconds($ttlSeconds ?? $this->ttlSeconds()));
    }

    /**
     * Ручная очистка: если используешь без tag-cache.
     * (Опционально; обычно dependency hash сам решит проблему.)
     */
    public function forget(string $key): void
    {
        Cache::forget($key);
    }
}
