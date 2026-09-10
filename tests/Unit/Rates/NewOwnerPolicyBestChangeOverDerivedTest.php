<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\AdminRateSourcePresenter;
use App\Services\Rates\BestChangeMarketBaseHealth;
use App\Services\Rates\ProtectedMarketBaselineWriteGuard;
use PHPUnit\Framework\TestCase;

/**
 * NEW OWNER POLICY: usable BestChange position → BESTCHANGE owns.
 * Derived is fallback only. Commercial delta / old protection flags are not
 * sufficient alone to keep Derived when BC can price the configured position.
 */
final class NewOwnerPolicyBestChangeOverDerivedTest extends TestCase
{
    public function test_health_evaluate_source_no_longer_fails_closed_on_outlier(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3) . '/app/Services/Rates/BestChangeMarketBaseHealth.php');
        $this->assertStringContainsString('outlier_vs_derived is a commercial/warning signal only', $src);
        $this->assertStringContainsString('NEW OWNER POLICY', $src);
        // Must not early-return unhealthy solely for outlier_vs_derived.
        $this->assertDoesNotMatchRegularExpression(
            "/reason'\\s*=>\\s*'outlier_vs_derived'[\\s\\S]{0,120}return \\$base;/",
            $src
        );
        $this->assertSame(12.0, BestChangeMarketBaseHealth::DEFAULT_OUTLIER_MAX_PERCENT);
    }

    public function test_derived_admin_label_never_leaks_raw_constant(): void
    {
        $d = new \App\Models\DirectionExchange();
        $d->parser_source_name = 'DERIVED_MARKET_BASELINE';
        $label = AdminRateSourcePresenter::label($d);
        $this->assertSame('Внутренний рынок · AUTO', $label);
        $this->assertStringNotContainsString('DERIVED_MARKET_BASELINE', $label);
    }

    public function test_derived_admin_label_notes_missing_bc_position_when_link_unusable(): void
    {
        $d = new \App\Models\DirectionExchange();
        $d->parser_source_name = 'DERIVED_MARKET_BASELINE';
        $bc = new \App\Models\BestChangeDirection();
        $bc->status = 0;
        $bc->is_error_parser = 1;
        $bc->rate_value = '0';
        $bc->position_num = '3';
        $d->setRelation('bestchange_directions', $bc);

        $label = AdminRateSourcePresenter::label($d);
        $this->assertStringContainsString('Внутренний рынок · AUTO', $label);
        $this->assertStringContainsString('BestChange: нет доступной позиции', $label);
        $this->assertStringNotContainsString('DERIVED_MARKET_BASELINE', $label);
    }

    public function test_recalculate_still_requires_health_but_health_ignores_commercial_delta(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3) . '/packages/BestChange/Services/DirectionExchangeRecalculateService.php'
        );
        $this->assertStringContainsString('BestChangeMarketBaseHealth::evaluate', $src);
        $this->assertStringContainsString('if (!$health[\'healthy\'])', $src);
    }

    public function test_protection_guard_still_honors_config_flag_when_set(): void
    {
        // Guard remains as a mechanical write gate; migration clears the flag
        // only after proving a usable BC position (see phase9 migratable).
        $src = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/Rates/ProtectedMarketBaselineWriteGuard.php'
        );
        $this->assertStringContainsString('block_bestchange_overwrite', $src);
        $this->assertTrue(method_exists(ProtectedMarketBaselineWriteGuard::class, 'blocksBestchangeOverwrite'));
    }

    public function test_derived_apply_skips_when_bc_active_regardless_of_commercial_policy(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/Rates/DerivedMarketBaselineAuthority.php'
        );
        $this->assertStringContainsString("BestChangeMarketBaseHealth::hasActiveLink(\$directionId)", $src);
        $this->assertStringContainsString("'skipped' => 'bestchange_active'", $src);
    }
}
