<?php

declare(strict_types=1);

namespace App\Services\Rates;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Guard for using BestChange competitor position as BASE.
 */
final class BestChangeMarketBaseHealth
{
    public const DEFAULT_MAX_AGE_SECONDS = 1800;

    /**
     * Informational only: large BC↔derived delta must NOT block BestChange ownership.
     * Owner policy: usable configured BC position → BESTCHANGE owns the pair.
     */
    public const DEFAULT_OUTLIER_MAX_PERCENT = 12.0;

    /**
     * @return array{
     *   healthy: bool,
     *   status: string,
     *   reason: ?string,
     *   direction_id: int,
     *   rate_value: ?string,
     *   position_num: ?string,
     *   updated_at: ?string,
     *   age_seconds: ?int,
     *   link_status: ?int,
     *   derived_reference: ?string,
     *   outlier_vs_derived: bool,
     *   outlier_delta_percent: ?float
     * }
     */
    public static function evaluate(int $directionId, ?int $maxAgeSeconds = null): array
    {
        $maxAge = $maxAgeSeconds ?? self::DEFAULT_MAX_AGE_SECONDS;
        $base = [
            'healthy' => false,
            'status' => 'unavailable',
            'reason' => null,
            'direction_id' => $directionId,
            'rate_value' => null,
            'position_num' => null,
            'updated_at' => null,
            'age_seconds' => null,
            'link_status' => null,
            'derived_reference' => null,
            'outlier_vs_derived' => false,
            'outlier_delta_percent' => null,
        ];

        if ($directionId <= 0) {
            $base['reason'] = 'invalid_direction';

            return $base;
        }

        // Prefer the active mapping when historical duplicate rows exist.
        // Duplicate groups currently have at most one active row; this removes
        // accidental first()-row ambiguity without deleting history.
        $bc = DB::table('bestchange_directions')
            ->where('id_direction_exchange', $directionId)
            ->orderByDesc('status')
            ->orderBy('id')
            ->first();

        if ($bc === null) {
            $base['reason'] = 'link_missing';
            $base['status'] = 'missing';

            return $base;
        }

        // Outlier vs derived is a commercial/warning signal only and must not
        // deny BestChange ownership when the link is active/fresh/positive.
        return self::evaluateFromRow($bc, $directionId, $maxAge, withDerivedOutlier: true);
    }

    public static function isHealthy(int $directionId, ?int $maxAgeSeconds = null): bool
    {
        return self::evaluate($directionId, $maxAgeSeconds)['healthy'] === true;
    }

    /**
     * Active BestChange mapping is a hard ownership boundary.
     * Health (stale/error/outlier) must not transfer write rights to derived.
     */
    public static function isActiveStatus(mixed $status): bool
    {
        return (int) ($status ?? 0) === 1;
    }

    public static function hasActiveLink(int $directionId): bool
    {
        if ($directionId <= 0) {
            return false;
        }

        $status = DB::table('bestchange_directions')
            ->where('id_direction_exchange', $directionId)
            ->orderByDesc('status')
            ->orderBy('id')
            ->value('status');

        return self::isActiveStatus($status);
    }

    private static function ageSeconds(mixed $updatedAt): ?int
    {
        if ($updatedAt === null || $updatedAt === '') {
            return null;
        }
        // bestchange_directions.updated_at is a naive DATETIME stamped by the PHP
        // writer in APP_TIMEZONE (Europe/Moscow). MySQL SYSTEM/NOW() on this host
        // is not Moscow (observed UTC+2), so TIMESTAMPDIFF(..., NOW()) yields
        // negative ages and hides real staleness. Always age in APP_TIMEZONE.
        try {
            $tz = self::appTimezone();
            $updated = Carbon::parse((string) $updatedAt, $tz);
            $now = Carbon::now($tz);
            if ($updated->greaterThan($now)) {
                return 0;
            }

            return (int) $updated->diffInSeconds($now);
        } catch (Throwable) {
            return null;
        }
    }

    private static function appTimezone(): string
    {
        try {
            if (function_exists('config')) {
                $tz = config('app.timezone');
                if (is_string($tz) && $tz !== '') {
                    return $tz;
                }
            }
        } catch (Throwable) {
            // unit tests / missing container
        }

        return 'UTC';
    }

    /**
     * Evaluate from a preloaded bestchange_directions row (batch path).
     * Skips derived-outlier cross-check — informational only and expensive at scan scale.
     *
     * @return array{
     *   healthy: bool,
     *   status: string,
     *   reason: ?string,
     *   direction_id: int,
     *   rate_value: ?string,
     *   position_num: ?string,
     *   updated_at: ?string,
     *   age_seconds: ?int,
     *   link_status: ?int,
     *   derived_reference: ?string,
     *   outlier_vs_derived: bool,
     *   outlier_delta_percent: ?float
     * }
     */
    public static function evaluateFromRow(object $bc, int $directionId, ?int $maxAgeSeconds = null, bool $withDerivedOutlier = false): array
    {
        $maxAge = $maxAgeSeconds ?? self::DEFAULT_MAX_AGE_SECONDS;
        $base = [
            'healthy' => false,
            'status' => 'unavailable',
            'reason' => null,
            'direction_id' => $directionId,
            'rate_value' => null,
            'position_num' => null,
            'updated_at' => null,
            'age_seconds' => null,
            'link_status' => null,
            'derived_reference' => null,
            'outlier_vs_derived' => false,
            'outlier_delta_percent' => null,
            'explain_reason' => null,
            'book_depth' => null,
            'book_age_stale' => false,
            'is_error_parser' => false,
        ];

        $base['link_status'] = (int) ($bc->status ?? 0);
        $base['position_num'] = isset($bc->position_num) ? (string) $bc->position_num : null;
        $base['rate_value'] = isset($bc->rate_value) ? (string) $bc->rate_value : null;
        $base['updated_at'] = isset($bc->updated_at) ? (string) $bc->updated_at : null;
        $base['is_error_parser'] = (int) ($bc->is_error_parser ?? 0) === 1;

        $age = self::ageSeconds($bc->updated_at ?? null);
        $base['age_seconds'] = $age;
        $base['book_age_stale'] = $age === null || $age > $maxAge;

        if (isset($bc->explain_payload) && $bc->explain_payload !== null && $bc->explain_payload !== '') {
            $exp = is_array($bc->explain_payload)
                ? $bc->explain_payload
                : (json_decode((string) $bc->explain_payload, true) ?: null);
            if (is_array($exp)) {
                $base['explain_reason'] = isset($exp['reason']) ? (string) $exp['reason'] : null;
                if (isset($exp['available_offers']) && is_numeric($exp['available_offers'])) {
                    $base['book_depth'] = (int) $exp['available_offers'];
                }
            }
        }

        if ((int) ($bc->status ?? 0) !== 1) {
            $base['reason'] = 'link_inactive';
            $base['status'] = 'inactive';

            return $base;
        }

        // Parser failure is independent of heartbeat age (fresh ingest can still be thin).
        if ($base['is_error_parser']) {
            $explain = (string) ($base['explain_reason'] ?? '');
            $base['reason'] = $explain === 'position_insufficient_depth'
                ? 'position_insufficient_depth'
                : 'parser_error';
            $base['status'] = 'error';

            return $base;
        }

        $rate = trim((string) ($bc->rate_value ?? '0'));
        if ($rate === '' || ! is_numeric($rate) || bccomp($rate, '0', 18) <= 0) {
            $base['reason'] = 'non_positive_rate';
            $base['status'] = 'invalid';

            return $base;
        }

        if ($base['book_age_stale']) {
            $base['reason'] = 'stale';
            $base['status'] = 'stale';

            return $base;
        }

        if ($withDerivedOutlier) {
            $derivedRef = self::derivedReferenceRate($directionId);
            $base['derived_reference'] = $derivedRef;
            if ($derivedRef !== null && is_numeric($derivedRef) && bccomp($derivedRef, '0', 12) > 0) {
                $diff = self::absRelativePercent($rate, $derivedRef);
                $base['outlier_delta_percent'] = $diff;
                if ($diff !== null && $diff > self::DEFAULT_OUTLIER_MAX_PERCENT) {
                    $base['outlier_vs_derived'] = true;
                }
            }
        }

        $base['healthy'] = true;
        $base['status'] = 'healthy';
        $base['reason'] = null;

        return $base;
    }

    private static function derivedReferenceRate(int $directionId): ?string
    {
        try {
            if (!DerivedMarketBaselineAuthority::fromStorageApp()->owns($directionId)) {
                return null;
            }
            $eval = DerivedMarketBaselineAuthority::fromStorageApp()->evaluate($directionId);
            if (!empty($eval['ok']) && isset($eval['rate']) && is_numeric((string) $eval['rate'])) {
                return (string) $eval['rate'];
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private static function absRelativePercent(string $a, string $b): ?float
    {
        if (!is_numeric($a) || !is_numeric($b) || bccomp($b, '0', 12) <= 0) {
            return null;
        }
        $diff = bcsub($a, $b, 12);
        if (str_starts_with($diff, '-')) {
            $diff = substr($diff, 1);
        }

        return (float) bcmul(bcdiv($diff, $b, 12), '100', 6);
    }
}
