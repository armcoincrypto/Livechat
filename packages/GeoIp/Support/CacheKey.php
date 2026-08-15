<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Support;

final class CacheKey
{
    public static function data(string $prefix, string $type, string $ip, string $locale): string
    {
        return $prefix.$type.':'.$locale.':'.$ip;
    }

    public static function lock(string $key): string
    {
        return $key.':lock';
    }
}
