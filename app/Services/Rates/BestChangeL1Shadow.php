<?php

declare(strict_types=1);

namespace App\Services\Rates;

use Illuminate\Support\Facades\Log;

/**
 * L1 BestChange optimization controls (shadow / skip-identical).
 *
 * Defaults OFF. Never owns customer rates. Shadow discards optimized results.
 *
 * Same-snapshot algorithmic equivalence uses a separate flag:
 * BESTCHANGE_L1_SAME_SNAPSHOT_DIAGNOSTIC (see BestChangeL1SameSnapshotDiagnostic).
 * Do not overload authority / shadow / skip-identical flags for that harness.
 */
final class BestChangeL1Shadow
{
    public static function shadowEnabled(): bool
    {
        return self::flagBool('BESTCHANGE_L1_SHADOW_ENABLED', 'bestchange_l1.shadow_enabled', false);
    }

    /**
     * When true, DirectionExchangeRecalculateService skips DE upserts whose
     * semantic fields are unchanged. Default false — updated_at heartbeat
     * remains production behavior until explicitly enabled.
     */
    public static function skipIdenticalDeUpsertEnabled(): bool
    {
        return self::flagBool('BESTCHANGE_L1_SKIP_IDENTICAL_DE_UPSERT', 'bestchange_l1.skip_identical_de_upsert', false);
    }

    /**
     * Process getenv wins (ops/artisan session); else config/bestchange_l1.php (CC1-safe).
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

    /**
     * @param  array<string, mixed>  $authAdj
     * @param  array<string, mixed>  $shadowAdj
     * @return array{match: bool, class: string}
     */
    public static function classifyTopNMismatch(
        bool $booksEqual,
        string $authCourse,
        string $shadowCourse,
        array $authAdj,
        array $shadowAdj,
    ): array {
        if ($booksEqual
            && bccomp($authCourse, $shadowCourse, 18) === 0
            && (string) ($authAdj['reason'] ?? '') === (string) ($shadowAdj['reason'] ?? '')
            && (bool) ($authAdj['applied'] ?? false) === (bool) ($shadowAdj['applied'] ?? false)
        ) {
            return ['match' => true, 'class' => 'OK'];
        }

        if (! $booksEqual) {
            if (bccomp($authCourse, $shadowCourse, 18) === 0) {
                return ['match' => true, 'class' => 'BOOK_DIFF_COURSE_SAME'];
            }

            return ['match' => false, 'class' => 'TIMING_RACE'];
        }

        if ((string) ($authAdj['status'] ?? '') !== (string) ($shadowAdj['status'] ?? '')) {
            return ['match' => false, 'class' => 'TOPN_ELIGIBILITY'];
        }

        if (bccomp($authCourse, $shadowCourse, 18) !== 0) {
            return ['match' => false, 'class' => 'ROUNDING'];
        }

        return ['match' => false, 'class' => 'UNKNOWN'];
    }

    /**
     * @param  array<string, int|float|string>  $metrics
     */
    public static function logGenerationMetrics(array $metrics): void
    {
        Log::info('bestchange_l1_generation_metrics', $metrics);
    }
}
