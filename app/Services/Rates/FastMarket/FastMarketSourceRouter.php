<?php

declare(strict_types=1);

namespace App\Services\Rates\FastMarket;

use App\Models\DirectionExchange;
use iEXPackages\Calculator\SourceChecks;

/**
 * Explicit per-family routing for the fast quote plane.
 *
 * Does not change source ownership semantics — MANUAL stays manual,
 * ZELLE stays Zelle-owned, DERIVED uses its baseline, BESTCHANGE uses
 * the shared market snapshot when available.
 */
class FastMarketSourceRouter
{
    public const FAMILY_BESTCHANGE = 'BESTCHANGE';

    public const FAMILY_DERIVED = 'DERIVED';

    public const FAMILY_ZELLE = 'ZELLE';

    public const FAMILY_MANUAL = 'MANUAL';

    public const FAMILY_COMPETITOR = 'COMPETITOR';

    public const FAMILY_OTHER = 'OTHER';

    /**
     * @return array{
     *   family: string,
     *   fast_source_available: bool,
     *   target_refresh: string,
     *   calculation_owner: string,
     *   fallback: string,
     *   use_bestchange_snapshot: bool
     * }
     */
    public function route(DirectionExchange $direction): array
    {
        if (SourceChecks::zelle($direction)) {
            return [
                'family' => self::FAMILY_ZELLE,
                'fast_source_available' => false,
                'target_refresh' => 'existing_zelle_jobs',
                'calculation_owner' => 'CalculatorFacade+ZelleBaselineStrategy',
                'fallback' => 'persisted_course_value',
                'use_bestchange_snapshot' => false,
            ];
        }

        if (SourceChecks::derived($direction)) {
            return [
                'family' => self::FAMILY_DERIVED,
                'fast_source_available' => false,
                'target_refresh' => 'derived_baseline_refresh',
                'calculation_owner' => 'CalculatorFacade+DerivedBaselineStrategy',
                'fallback' => 'persisted_course_value',
                'use_bestchange_snapshot' => false,
            ];
        }

        if (SourceChecks::bestchange($direction)) {
            return [
                'family' => self::FAMILY_BESTCHANGE,
                'fast_source_available' => true,
                'target_refresh' => '30-60s_snapshot',
                'calculation_owner' => 'BestChangeBookRateComputer+CalculatorFacade',
                'fallback' => 'persisted_rate_value_if_age_ok',
                'use_bestchange_snapshot' => true,
            ];
        }

        if (SourceChecks::manual($direction)) {
            return [
                'family' => self::FAMILY_MANUAL,
                'fast_source_available' => false,
                'target_refresh' => 'admin_only',
                'calculation_owner' => 'CalculatorFacade+ManualStrategy',
                'fallback' => 'persisted_manual_rate',
                'use_bestchange_snapshot' => false,
            ];
        }

        if (SourceChecks::competitor($direction)) {
            return [
                'family' => self::FAMILY_COMPETITOR,
                'fast_source_available' => false,
                'target_refresh' => 'competitors_cron',
                'calculation_owner' => 'CalculatorFacade+CompetitorStrategy',
                'fallback' => 'persisted_course_value',
                'use_bestchange_snapshot' => false,
            ];
        }

        return [
            'family' => self::FAMILY_OTHER,
            'fast_source_available' => false,
            'target_refresh' => 'persisted',
            'calculation_owner' => 'CalculatorFacade',
            'fallback' => 'persisted',
            'use_bestchange_snapshot' => false,
        ];
    }
}
