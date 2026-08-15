<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Support;

/**
 * L1 in-memory кеш (на время жизни процесса).
 */
final class LocalCache
{
    private static array $data = [];
    private static array $order = [];

    public static function get(string $key): mixed
    {
        return self::$data[$key] ?? null;
    }

    public static function put(string $key, mixed $value, int $maxItems = 5000): void
    {
        if (!array_key_exists($key, self::$data)) {
            self::$order[] = $key;
        }
        self::$data[$key] = $value;

        while (count(self::$order) > $maxItems) {
            $old = array_shift(self::$order);
            if ($old !== null) {
                unset(self::$data[$old]);
            }
        }
    }
}
