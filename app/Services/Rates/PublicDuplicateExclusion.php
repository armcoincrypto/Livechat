<?php

declare(strict_types=1);

namespace App\Services\Rates;

/**
 * Release B1: exclude proven public-duplicate direction IDs from public surfaces
 * without deleting or updating database rows.
 */
final class PublicDuplicateExclusion
{
    /** @var array{exclude:array<int,true>,replacements:array<int,int>}|null */
    private static ?array $cache = null;

    public static function isExcluded(?int $directionId): bool
    {
        if ($directionId === null || $directionId <= 0) {
            return false;
        }

        return isset(self::map()['exclude'][$directionId]);
    }

    public static function canonicalReplacement(?int $directionId): ?int
    {
        if ($directionId === null || $directionId <= 0) {
            return null;
        }

        return self::map()['replacements'][$directionId] ?? null;
    }

    /** @internal tests / hot reload after config file change */
    public static function clearCache(): void
    {
        self::$cache = null;
    }

    /**
     * @return array{exclude:array<int,true>,replacements:array<int,int>}
     */
    private static function map(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = base_path('resources/rates/public-duplicate-exclusions.json');
        $exclude = [];
        $replacements = [];
        if (is_file($path)) {
            $json = json_decode((string) file_get_contents($path), true);
            foreach (($json['exclude_direction_ids'] ?? []) as $id) {
                $exclude[(int) $id] = true;
            }
            foreach (($json['canonical_replacements'] ?? []) as $from => $to) {
                $replacements[(int) $from] = (int) $to;
            }
        }

        return self::$cache = ['exclude' => $exclude, 'replacements' => $replacements];
    }
}
