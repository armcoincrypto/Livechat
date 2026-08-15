<?php

declare(strict_types=1);

namespace iEXPackages\Courses\Concerns;

use iEXPackages\Courses\CoursesResponse;
use Psr\SimpleCache\InvalidArgumentException;
use Throwable;

trait ManualCompilers
{
    /**
     * Ручная генерация и возврат initial-данных курсов в JSON.
     *
     * Как работает:
     * - Сначала пытаемся взять данные из cache store.
     * - Если в кеше нет данных (или они некорректны) — пересобираем через builder() и сохраняем.
     * - Возвращаем JSON строку независимо от способа получения.
     *
     * @param string|null $store Название cache store (например: redis|array). Если null — берём из withData() или 'redis'.
     *
     * @return string JSON
     * @throws InvalidArgumentException
     */
    public function manualInitial(?string $store = null): string
    {
        $store = $this->normalizeStore($store);

        $cacheKey = 'exchange-iex-initial-rates-' . \Str::lower($this->getLocale());

        try {
            $cached = \Cache::store($store)->get($cacheKey);

            // Если кеш уже содержит массив — просто отдаём.
            if (is_array($cached) && $cached !== []) {
                return (new CoursesResponse($cached))->toJson();
            }

            // Если кеш содержит строку (редко, но бывает) — отдаём как есть.
            if (is_string($cached) && trim($cached) !== '') {
                return $cached;
            }
        } catch (Throwable $e) {
            // Любая ошибка кеша не должна ломать ручной вызов — просто пересобираем
        }

        // Если в кеше нет данных — пересобираем
        $this->withData(['cache' => $store]);
        $this->builder();

        // После builder() берём то, что он сформировал (для array store ты уже сохраняешь в setInitialResponse)
        $built = $this->getInitialResponse();

        if (is_array($built) && $built !== []) {
            return (new CoursesResponse($built))->toJson();
        }

        // Фоллбек: пробуем снова из кеша
        $cached = \Cache::store($store)->get($cacheKey);

        if (is_array($cached)) {
            return (new CoursesResponse($cached))->toJson();
        }

        if (is_string($cached) && trim($cached) !== '') {
            return $cached;
        }

        // Последний фоллбек — понятная ошибка в JSON
        return json_encode([
            'error' => 'initial_response_empty',
            'store' => $store,
            'key' => $cacheKey,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Нормализует cache store.
     *
     * @param string|null $store
     * @return string
     */
    private function normalizeStore(?string $store): string
    {
        $store = trim((string) $store);

        if ($store !== '') {
            return $store;
        }

        // берём из context, если уже задано
        if (property_exists($this, 'context') && is_array($this->context ?? null)) {
            $ctxStore = trim((string) ($this->context['cache'] ?? ''));
            if ($ctxStore !== '') {
                return $ctxStore;
            }
        }

        return 'redis';
    }
}
