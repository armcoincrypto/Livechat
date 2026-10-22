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
 */
final class BestChangeL1Authority
{
    public const FLAG = 'BESTCHANGE_L1_AUTHORITY_ENABLED';

    public static function enabled(): bool
    {
        return self::envBool(self::FLAG, false);
    }

    private static function envBool(string $key, bool $default): bool
    {
        $raw = function_exists('env') ? env($key, null) : null;
        if ($raw === null) {
            $g = getenv($key);
            $raw = ($g === false) ? null : $g;
        }
        if ($raw === null) {
            return $default;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }
}
