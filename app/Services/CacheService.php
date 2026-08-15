<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use Exception;

class CacheService
{
    /**
     * Получить из кеша значение по ключу с возможностью указать теги.
     */
    public function get(string $key, array $tags = [], $default = null)
    {
        try {
            return empty($tags)
                ? Cache::get($key, $default)
                : Cache::tags($tags)->get($key, $default);
        } catch (Exception $e) {
            report($e);
            return $default;
        }
    }

    /**
     * Вспомогательный метод для получения времени истечения кеша сразу в минутах.
     */
    protected function ttlInMinutes(int $minutes): Carbon
    {
        return Carbon::now()->addMinutes($minutes);
    }

    /**
     * Сохранить значение в кеш с тегами и возможностью указать TTL (в минутах).
     */
    public function put(string $key, $value, array $tags = [], int $minutes = 60): bool
    {
        try {
            $expiration = $this->ttlInMinutes($minutes);

            return empty($tags)
                ? Cache::put($key, $value, $expiration)
                : Cache::tags($tags)->put($key, $value, $expiration);
        } catch (Exception $e) {
            report($e);
            return false;
        }
    }

    /**
     * Получить данные или сохранить, если их нет в кеше с TTL в минутах.
     */
    public function remember(string $key, callable $callback, array $tags = [], int $minutes = 60)
    {
        try {
            $expiration = $this->ttlInMinutes($minutes);

            return empty($tags)
                ? Cache::remember($key, $expiration, $callback)
                : Cache::tags($tags)->remember($key, $expiration, $callback);
        } catch (Exception $e) {
            report($e);
            return $callback();
        }
    }

    /**
     * Проверка наличия ключа в кеше с учетом тегов.
     */
    public function has(string $key, array $tags = []): bool
    {
        try {
            return empty($tags)
                ? Cache::has($key)
                : Cache::tags($tags)->has($key);
        } catch (Exception $e) {
            report($e);
            return false;
        }
    }

    /**
     * Удалить конкретный ключ из кеша, поддерживает теги.
     */
    public function forget(string $key, array $tags = []): bool
    {
        try {
            return empty($tags)
                ? Cache::forget($key)
                : Cache::tags($tags)->forget($key);
        } catch (Exception $e) {
            report($e);
            return false;
        }
    }

    /**
     * Очистка кеша по тегам.
     */
    public function flushTags(array $tags): bool
    {
        try {
            Cache::tags($tags)->flush();
            return true;
        } catch (Exception $e) {
            report($e);
            return false;
        }
    }

    /**
     * Очистка всего кеша приложения.
     */
    public function flushAll(): bool
    {
        try {
            Cache::flush();
            return true;
        } catch (Exception $e) {
            report($e);
            return false;
        }
    }

    /**
     * Удалить множество ключей сразу (с учетом тегов).
     */
    public function forgetMultiple(array $keys, array $tags = []): void
    {
        foreach ($keys as $key) {
            $this->forget($key, $tags);
        }
    }

    /**
     * Универсальный метод кеширования, зависящий от внешнего условия.
     */
    public function cacheIf(bool $condition, string $key, callable $callback, array $tags = [], int $ttl = 3600)
    {
        if (!$condition) {
            return $callback();
        }

        return $this->remember($key, $callback, $tags, $ttl);
    }
}
