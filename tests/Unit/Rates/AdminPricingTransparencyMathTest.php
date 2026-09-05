<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Models\DirectionExchange;
use App\Services\Rates\CanonicalDirectionRateCalculator;
use App\Services\Rates\CommercialAdjustmentResolver;
use App\Services\Rates\RateChannel;
use App\Services\Rates\RateMode;
use App\Services\Rates\ZelleUsdUsdtBenchmarkAuthority;
use PHPUnit\Framework\TestCase;

/**
 * Wave C: customer-rate explanation must match backend math (no commercial mutations).
 */
final class AdminPricingTransparencyMathTest extends TestCase
{
    public function test_bestchange_profit_and_fix_fee_stacking_semantics(): void
    {
        $d = new DirectionExchange();
        $d->id = 15;
        $d->parser_source_name = 'BestChange';
        $d->profit = '1';
        $d->floating_fee = '0';
        $d->fix_fee = '-3';
        $d->is_type_rate = 1;
        $d->course_value = '100';

        $resolver = new CommercialAdjustmentResolver();
        $this->assertSame('BESTCHANGE', $resolver->family($d));
        $this->assertSame('1', $resolver->displayProfitPercent($d));
        $this->assertSame('profit', $resolver->storageField($d));

        // Canonical floating: BestChange commercial is via Calculator profit path;
        // Canonical floating fee stays 0 to avoid double %.
        $calc = CanonicalDirectionRateCalculator::make();
        $floating = $calc->calculate($d, RateMode::Floating, RateChannel::Website, '100');
        $fixed = $calc->calculate($d, RateMode::Fixed, RateChannel::Website, '100');

        $this->assertSame('0', $floating->adjustmentValue);
        $this->assertSame(0, bccomp($floating->finalRate, '100', 8)); // profit applied outside Canonical for BC
        $this->assertSame('-3', $fixed->adjustmentValue);
        $this->assertSame(0, bccomp($fixed->finalRate, '97', 8));
    }

    public function test_derived_profit_maps_into_canonical_floating(): void
    {
        $d = new DirectionExchange();
        $d->id = 200;
        $d->parser_source_name = 'DERIVED_MARKET_BASELINE';
        $d->profit = '1';
        $d->floating_fee = '0';
        $d->fix_fee = '-3';
        $d->is_type_rate = 1;

        $calc = CanonicalDirectionRateCalculator::make();
        $floating = $calc->calculate($d, RateMode::Floating, RateChannel::Website, '100');
        $fixed = $calc->calculate($d, RateMode::Fixed, RateChannel::Website, '100');

        // Derived: profit=+1 ⇒ fee=-1% ⇒ 99
        $this->assertSame(0, bccomp($floating->finalRate, '99', 8));
        $this->assertSame(0, bccomp($fixed->finalRate, '97', 8));
    }

    public function test_zelle_floating_fee_is_display_profit_negated_and_fixed_stacks(): void
    {
        $d = new DirectionExchange();
        $d->id = 1519;
        $d->parser_source_name = ZelleUsdUsdtBenchmarkAuthority::PARSER_SOURCE_NAME;
        $d->id_currency1 = 87;
        $d->profit = '0';
        $d->floating_fee = '-6';
        $d->fix_fee = '-3';
        $d->is_type_rate = 1;

        $resolver = new CommercialAdjustmentResolver();
        $this->assertSame('ZELLE', $resolver->family($d));
        $this->assertSame('6', $resolver->displayProfitPercent($d));
        $this->assertSame('floating_fee', $resolver->storageField($d));

        $calc = CanonicalDirectionRateCalculator::make();
        $floating = $calc->calculate($d, RateMode::Floating, RateChannel::Website, '100');
        $fixed = $calc->calculate($d, RateMode::Fixed, RateChannel::Website, '100');

        // floating: 100 * (1 - 0.06) = 94
        $this->assertSame(0, bccomp($floating->finalRate, '94', 8));
        // fixed: floating first then fix_fee -3% ⇒ 94 * 0.97
        $this->assertSame(0, bccomp($fixed->finalRate, '91.18', 8));
    }

    public function test_zero_profit_wording_must_not_imply_no_margin_when_fix_fee_active(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3) . '/app/Services/Rates/AdminPricingTransparency.php');
        $this->assertStringContainsString('Явная прибыль: 0%', $src);
        $this->assertStringContainsString('Другие корректировки: активны', $src);
        $this->assertStringNotContainsString('без прибыли', $src);
    }
}
