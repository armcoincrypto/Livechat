<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Support;

final class SafeExtractor
{
    public static function name(?object $record, string $locale): ?string
    {
        if (!$record) return null;

        $names = $record->names ?? null;
        if (is_array($names)) {
            $v = $names[$locale] ?? null;
            if (is_string($v)) {
                $v = trim($v);
                if ($v !== '') return self::limit($v);
            }
        }

        $name = $record->name ?? null;
        if (is_string($name)) {
            $name = trim($name);
            return $name !== '' ? self::limit($name) : null;
        }

        return null;
    }

    public static function str(mixed $v): ?string
    {
        if (!is_string($v)) return null;
        $x = trim($v);
        return $x === '' ? null : self::limit($x);
    }

    public static function int(mixed $v): ?int
    {
        return is_int($v) ? $v : (is_numeric($v) ? (int)$v : null);
    }

    public static function float(mixed $v): ?float
    {
        return is_float($v) ? $v : (is_numeric($v) ? (float)$v : null);
    }

    public static function bool(mixed $v): ?bool
    {
        return is_bool($v) ? $v : null;
    }

    private static function limit(string $v, int $max = 255): string
    {
        return mb_strlen($v) > $max ? mb_substr($v, 0, $max) : $v;
    }
}
