<?php

declare(strict_types=1);

namespace App\Services\Rates;

/**
 * L1 generation-book authority gate for Top-N selection.
 *
 * When enabled, the BestChange compiler generation book is the preferred
 * source for Top-N adjust() — avoiding a duplicate per-direction network
 * fetch when the book is already in BestChangeCompilerGenerationContext.
 *
 * When disabled (default), falls back to marketForDirection per direction
 * (historical authoritative path). Distinct from:
 * - BESTCHANGE_L1_SAME_SNAPSHOT_DIAGNOSTIC (read-only equivalence harness)
 * - BESTCHANGE_L1_SHADOW_ENABLED (cross-time shadow)
 * - BESTCHANGE_L1_SKIP_IDENTICAL_DE_UPSERT (upsert heartbeat optimization)
 *
 * Fail-safe: unavailable/empty generation book → marketForDirection.
 *
 * CC1: prefer process getenv (artisan/ops session override), else config/bestchange_l1.php.
 */
final class BestChangeL1Authority
{
    public const FLAG = 'BESTCHANGE_L1_AUTHORITY_ENABLED';

    public static function enabled(): bool
    {
        return self::flagBool(self::FLAG, 'bestchange_l1.authority_enabled', false);
    }

    /**
     * @param  non-empty-string  $envKey
     * @param  non-empty-string  $configKey
     */
    private static function flagBool(string $envKey, string $configKey, bool $default): bool
    {
        $g = getenv($envKey);
        if ($g !== false && $g !== '') {
            return filter_var($g, FILTER_VALIDATE_BOOLEAN);
        }

        $cfg = config($configKey, null);
        if ($cfg !== null) {
            return filter_var($cfg, FILTER_VALIDATE_BOOLEAN);
        }

        return $default;
    }
}
