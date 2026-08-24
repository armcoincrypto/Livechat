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
     *   derived_reference: ?string
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
        ];

        if ($directionId <= 0) {
            $base['reason'] = 'invalid_direction';

            return $base;
        }

        $bc = DB::table('bestchange_directions')
            ->where('id_direction_exchange', $directionId)
            ->first();

        if ($bc === null) {
            $base['reason'] = 'link_missing';
            $base['status'] = 'missing';

            return $base;
        }

        $base['link_status'] = (int) ($bc->status ?? 0);
        $base['position_num'] = isset($bc->position_num) ? (string) $bc->position_num : null;
        $base['rate_value'] = isset($bc->rate_value) ? (string) $bc->rate_value : null;
        $base['updated_at'] = isset($bc->updated_at) ? (string) $bc->updated_at : null;

        if ((int) ($bc->status ?? 0) !== 1) {
            $base['reason'] = 'link_inactive';
            $base['status'] = 'inactive';

            return $base;
        }

        if ((int) ($bc->is_error_parser ?? 0) === 1) {
            $base['reason'] = 'parser_error';
            $base['status'] = 'error';

            return $base;
        }

        $rate = trim((string) ($bc->rate_value ?? '0'));
        if ($rate === '' || !is_numeric($rate) || bccomp($rate, '0', 18) <= 0) {
            $base['reason'] = 'non_positive_rate';
            $base['status'] = 'invalid';

            return $base;
        }

        $age = self::ageSeconds($bc->updated_at ?? null);
        $base['age_seconds'] = $age;
        if ($age === null || $age > $maxAge) {
            $base['reason'] = 'stale';
            $base['status'] = 'stale';

            return $base;
        }

        $derivedRef = self::derivedReferenceRate($directionId);
        $base['derived_reference'] = $derivedRef;
        if ($derivedRef !== null && is_numeric($derivedRef) && bccomp($derivedRef, '0', 12) > 0) {
            $diff = self::absRelativePercent($rate, $derivedRef);
            if ($diff !== null && $diff > self::DEFAULT_OUTLIER_MAX_PERCENT) {
                $base['reason'] = 'outlier_vs_derived';
                $base['status'] = 'outlier';

                return $base;
            }
        }

        $base['healthy'] = true;
        $base['status'] = 'healthy';
        $base['reason'] = null;

        return $base;
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
            ->value('status');

        return self::isActiveStatus($status);
    }

    private static function ageSeconds(mixed $updatedAt): ?int
    {
        if ($updatedAt === null || $updatedAt === '') {
            return null;
        }
        try {
            return (int) max(0, Carbon::parse((string) $updatedAt)->diffInSeconds(Carbon::now()));
        } catch (Throwable) {
            return null;
        }
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
