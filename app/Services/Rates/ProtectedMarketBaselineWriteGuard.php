<?php

declare(strict_types=1);

namespace App\Services\Rates;

use Illuminate\Support\Facades\Log;

/**
 * Fail-closed ownership gate for directions whose BASE is owned by
 * {@see DerivedMarketBaselineAuthority} with block_bestchange_overwrite.
 *
 * BestChange recalculate historically re-poisoned GRAM/TON (and peers) whenever
 * an admin/BC link was status=1, ignoring the config flag. Protection must not
 * rely solely on disabling bestchange_directions.status.
 */
final class ProtectedMarketBaselineWriteGuard
{
    public const AUTHORITY = 'DERIVED_MARKET_BASELINE';

    /**
     * @var array<int, true>|null
     */
    private static ?array $blockedIdCache = null;

    public static function clearCache(): void
    {
        self::$blockedIdCache = null;
    }

    public static function blocksBestchangeOverwrite(?int $directionId): bool
    {
        if ($directionId === null || $directionId <= 0) {
            return false;
        }

        return isset(self::blockedIdMap()[$directionId]);
    }

    /**
     * @return array{blocked:bool,reason:?string,direction_id:?int}
     */
    public static function denyBestchangeWrite(?int $directionId, string $writer): array
    {
        if (!self::blocksBestchangeOverwrite($directionId)) {
            return [
                'blocked' => false,
                'reason' => null,
                'direction_id' => $directionId,
            ];
        }

        Log::warning('protected_market_baseline_write_blocked', [
            'writer' => $writer,
            'direction_id' => $directionId,
            'authority' => self::AUTHORITY,
            'reason' => 'block_bestchange_overwrite',
        ]);

        RateWriteAuditLogger::record([
            'event' => 'write_blocked',
            'direction_id' => $directionId,
            'writer' => $writer,
            'source' => self::AUTHORITY,
            'reason' => 'block_bestchange_overwrite',
            'old_base' => null,
            'new_base' => null,
        ]);

        return [
            'blocked' => true,
            'reason' => 'protected_by_'.self::AUTHORITY.'_block_bestchange_overwrite',
            'direction_id' => $directionId,
        ];
    }

    /**
     * @return array<int, true>
     */
    private static function blockedIdMap(): array
    {
        if (self::$blockedIdCache !== null) {
            return self::$blockedIdCache;
        }

        $map = [];
        try {
            $cfg = DerivedMarketBaselineAuthority::fromStorageApp()->config()['directions'] ?? [];
            foreach ($cfg as $id => $row) {
                if (!empty($row['ownership']['block_bestchange_overwrite'])) {
                    $map[(int) $id] = true;
                }
            }
        } catch (\Throwable $e) {
            Log::error('protected_market_baseline_guard_config_failed', [
                'message' => $e->getMessage(),
            ]);
            // Fail closed: empty map would allow overwrites. Prefer empty only
            // when config is unreadable — callers still honor ZELLE guard.
        }

        self::$blockedIdCache = $map;

        return $map;
    }
}
