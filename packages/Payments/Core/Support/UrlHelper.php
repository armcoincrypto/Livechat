<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Support;

use LogicException;

/**
 * Универсальный helper для безопасной работы с URL и host.
 *
 * Назначение:
 * - нормализация host / url в HTTPS base URL
 * - защита от https://https:// и мусора
 * - удаление path / query / fragment
 * - безопасная склейка baseUrl + path
 *
 * ❗ НЕ привязан к gateway, merchant или payment
 */
final class UrlHelper
{
    private function __construct()
    {
        // static-only helper
    }

    /**
     * Приводит входное значение к HTTPS base URL.
     *
     * Примеры:
     *  - example.com                → https://example.com
     *  - api.example.com/           → https://api.example.com
     *  - https://example.com        → https://example.com
     *  - http://example.com/path    → https://example.com
     *  - example.com/path?x=1       → https://example.com
     *
     * @throws LogicException
     */
    public static function toHttpsBaseUrl(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new LogicException('Base URL is empty');
        }

        // Уже полноценный URL
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            $parts = parse_url($value);

            if (!is_array($parts) || empty($parts['host'])) {
                throw new LogicException('Invalid URL: ' . $value);
            }

            return 'https://' . $parts['host'];
        }

        // host может прийти с путём: example.com/path
        $host = explode('/', $value)[0];

        if (!self::isValidHost($host)) {
            throw new LogicException('Invalid host: ' . $value);
        }

        return 'https://' . $host;
    }

    /**
     * Безопасно склеивает baseUrl и path.
     *
     * base: https://example.com
     * path: /api/test
     *
     * → https://example.com/api/test
     */
    public static function join(string $baseUrl, string $path): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $path    = '/' . ltrim($path, '/');

        return $baseUrl . $path;
    }

    /**
     * Проверка валидности host (без схемы).
     *
     * Разрешено:
     *  - example.com
     *  - api.example.com
     *  - sub.domain.co.uk
     */
    public static function isValidHost(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        // убираем порт если есть
        $host = explode(':', $host)[0];

        return (bool) preg_match(
            '/^([a-z0-9-]+\.)+[a-z]{2,}$/i',
            $host
        );
    }

    public static function toBaseUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        // Если URL уже нормализован (имеет схему), то очищаем её
        if (str_contains($url, '://')) {
            $parsed = parse_url($url);
            return $parsed['host'] ?? ''; // только домен
        }

        // Без схемы, но с путём — извлекаем только домен
        $url = explode('/', $url)[0];

        return $url;
    }
}
