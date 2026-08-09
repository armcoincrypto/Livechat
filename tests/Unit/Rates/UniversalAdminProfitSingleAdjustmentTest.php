<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Models\DirectionExchange;
use App\Services\Rates\CanonicalDirectionRateCalculator;
use App\Services\Rates\CommercialAdjustmentResolver;
use App\Services\Rates\ZelleUsdUsdtBenchmarkAuthority;
use PHPUnit\Framework\TestCase;

/**
 * Release gate: every automatic family has exactly ONE effective floating
 * commercial adjustment path for admin «Прибыль». No profit+floating_fee stack.
 */
final class UniversalAdminProfitSingleAdjustmentTest extends TestCase
{
    public function test_zelle_never_stacks_profit_and_floating_fee(): void
    {
        $calc = new CanonicalDirectionRateCalculator();
        $d = new DirectionExchange();
        $d->id = 999001;
        $d->id_currency1 = 87;
        $d->parser_source_name = ZelleUsdUsdtBenchmarkAuthority::PARSER_SOURCE_NAME;
        $d->is_type_rate = 1;
        $d->course_value = '100';
        $d->profit = '0';
        $d->floating_fee = '-6';
        $d->fix_fee = '-3';

        $fee = $calc->resolveFloatingFeePercent($d);
        $this->assertSame('-6', $fee);

        // If profit were incorrectly set non-zero, Canonical prefers profit — that is
        // why admin must keep profit=0 and write floating_fee only.
        $d->profit = '1.5';
        $feeStackedPath = $calc->resolveFloatingFeePercent($d);
        $this->assertSame('-1.50000000', $feeStackedPath);
        $this->assertNotSame('-6', $feeStackedPath);

        $resolver = new CommercialAdjustmentResolver();
        $payload = $resolver->buildUpdatePayload($d, '6');
        $this->assertSame('0', $payload['profit']);
        $this->assertSame('-6', $payload['floating_fee']);
    }

    public function test_derived_uses_profit_and_clears_floating_fee_on_save(): void
    {
        $calc = new CanonicalDirectionRateCalculator();
        $d = new DirectionExchange();
        $d->parser_source_name = 'DERIVED_MARKET_BASELINE';
        $d->is_type_rate = 1;
        $d->profit = '1.5';
        $d->floating_fee = '0';

        $this->assertSame('-1.50000000', $calc->resolveFloatingFeePercent($d));

        $resolver = new CommercialAdjustmentResolver();
        $payload = $resolver->buildUpdatePayload($d, '1.5');
        $this->assertSame('1.5', $payload['profit']);
        $this->assertSame('0', $payload['floating_fee']);
    }

    public function test_display_profit_round_trip_preserves_zelle_six(): void
    {
        $resolver = new CommercialAdjustmentResolver();
        $d = new DirectionExchange();
        $d->id_currency1 = 87;
        $d->parser_source_name = ZelleUsdUsdtBenchmarkAuthority::PARSER_SOURCE_NAME;
        $d->profit = '0';
        $d->floating_fee = '-6';

        $display = $resolver->displayProfitPercent($d);
        $this->assertSame('6', $display);
        $again = $resolver->buildUpdatePayload($d, $display);
        $this->assertSame('-6', $again['floating_fee']);
        $this->assertSame('0', $again['profit']);
    }
}
