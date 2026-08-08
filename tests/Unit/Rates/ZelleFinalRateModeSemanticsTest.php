<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Models\DirectionExchange;
use App\Services\Rates\CanonicalDirectionRateCalculator;
use App\Services\Rates\RateChannel;
use App\Services\Rates\RateMode;
use App\Services\Rates\ZelleUsdBenchmarkResolver;
use PHPUnit\Framework\TestCase;

/**
 * Owner-approved ZELLEUSD mode semantics:
 * FLOATING = bench × (1+floating/100)
 * FIXED    = bench × (1+floating/100) × (1+fix/100)
 * XML      = FLOATING only
 */
final class ZelleFinalRateModeSemanticsTest extends TestCase
{
    public function test_minus6_minus3_at_benchmark_one(): void
    {
        $direction = $this->zelleDirection(floating: '-6', fix: '-3');
        $calc = CanonicalDirectionRateCalculator::make();

        $float = $calc->calculate($direction, RateMode::Floating, RateChannel::Website, '1');
        $fixed = $calc->calculate($direction, RateMode::Fixed, RateChannel::Website, '1');
        $export = $calc->calculateForExport($direction, '1');

        $this->assertSame(0, bccomp($float->finalRate, '0.94', 18));
        $this->assertSame(0, bccomp($fixed->finalRate, '0.9118', 18));
        $this->assertSame(0, bccomp($export->finalRate, '0.94', 18));
        $this->assertSame(RateMode::Floating, $export->mode);
        $this->assertTrue(bccomp($fixed->finalRate, $float->finalRate, 18) < 0);
    }

    public function test_floating_does_not_apply_fix_fee(): void
    {
        $direction = $this->zelleDirection(floating: '-6', fix: '-3');
        $float = CanonicalDirectionRateCalculator::make()
            ->calculate($direction, RateMode::Floating, RateChannel::Website, '100');

        $this->assertSame(0, bccomp($float->finalRate, '94', 18));
        $this->assertNotSame(0, bccomp($float->finalRate, '91.18', 18));
    }

    public function test_fixed_applies_floating_then_fix_once(): void
    {
        $direction = $this->zelleDirection(floating: '-6', fix: '-3');
        $fixed = CanonicalDirectionRateCalculator::make()
            ->calculate($direction, RateMode::Fixed, RateChannel::Order, '100');

        // Not fix alone (97), not floating alone (94), not double floating (88.36).
        $this->assertSame(0, bccomp($fixed->finalRate, '91.18', 18));
        $this->assertNotSame(0, bccomp($fixed->finalRate, '97', 18));
        $this->assertNotSame(0, bccomp($fixed->finalRate, '94', 18));
        $this->assertNotSame(0, bccomp($fixed->finalRate, '88.36', 18));
    }

    public function test_xml_never_includes_fix_fee_step(): void
    {
        $direction = $this->zelleDirection(floating: '-6', fix: '-3');
        $direction->file_rate_source = 'fix';

        $export = CanonicalDirectionRateCalculator::make()
            ->calculateForExport($direction, '1');

        $this->assertSame(0, bccomp($export->finalRate, '0.94', 18));
        $this->assertNotSame(0, bccomp($export->finalRate, '0.9118', 18));
        $this->assertNotSame(0, bccomp($export->finalRate, '0.97', 18));
    }

    public function test_admin_case_b_fix_minus2_no_code_change(): void
    {
        $direction = $this->zelleDirection(floating: '-6', fix: '-2');
        $calc = CanonicalDirectionRateCalculator::make();
        $float = $calc->calculate($direction, RateMode::Floating, RateChannel::Website, '1');
        $fixed = $calc->calculate($direction, RateMode::Fixed, RateChannel::Website, '1');

        $this->assertSame(0, bccomp($float->finalRate, '0.94', 18));
        $this->assertSame(0, bccomp($fixed->finalRate, '0.9212', 18)); // 0.94*0.98
    }

    public function test_admin_case_c_floating_minus5_fix_minus3(): void
    {
        $direction = $this->zelleDirection(floating: '-5', fix: '-3');
        $calc = CanonicalDirectionRateCalculator::make();
        $float = $calc->calculate($direction, RateMode::Floating, RateChannel::Website, '1');
        $fixed = $calc->calculate($direction, RateMode::Fixed, RateChannel::Website, '1');

        $this->assertSame(0, bccomp($float->finalRate, '0.95', 18));
        $this->assertSame(0, bccomp($fixed->finalRate, '0.9215', 18)); // 0.95*0.97
    }

    public function test_fix_fee_zero_means_fixed_equals_floating(): void
    {
        $direction = $this->zelleDirection(floating: '-6', fix: '0');
        $calc = CanonicalDirectionRateCalculator::make();
        $float = $calc->calculate($direction, RateMode::Floating, RateChannel::Website, '1');
        $fixed = $calc->calculate($direction, RateMode::Fixed, RateChannel::Website, '1');

        $this->assertSame(0, bccomp($float->finalRate, '0.94', 18));
        $this->assertSame(0, bccomp($fixed->finalRate, '0.94', 18));
    }

    public function test_website_payload_zelle_compounded_fixed(): void
    {
        $direction = $this->zelleDirection(floating: '-6', fix: '-3');
        $payload = CanonicalDirectionRateCalculator::make()
            ->websiteCoursePayload($direction, '1');

        $this->assertSame(0, bccomp((string) $payload['base_rate'], '1', 18));
        $this->assertSame(0, bccomp((string) $payload['floating_rate'], '0.94', 18));
        $this->assertSame(0, bccomp((string) $payload['fixed_rate'], '0.9118', 18));
        $this->assertSame(0, bccomp((string) $payload['display_rate'], '0.94', 18));
        // Admin fee fields remain raw configured percents.
        $this->assertSame('-6', $payload['fee']['floating']['value']);
        $this->assertSame('-3', $payload['fee']['fixed']['value']);
    }

    public function test_non_zelle_fixed_still_uses_fix_fee_alone(): void
    {
        $direction = new DirectionExchange();
        $direction->id = 81;
        $direction->id_currency1 = 3; // USDTTRC20 — not ZELLE
        $direction->is_type_rate = 1;
        $direction->floating_fee = '-6';
        $direction->fix_fee = '-3';

        $calc = CanonicalDirectionRateCalculator::make();
        $float = $calc->calculate($direction, RateMode::Floating, RateChannel::Website, '1');
        $fixed = $calc->calculate($direction, RateMode::Fixed, RateChannel::Website, '1');

        $this->assertSame(0, bccomp($float->finalRate, '0.94', 18));
        $this->assertSame(0, bccomp($fixed->finalRate, '0.97', 18));
        $this->assertNotSame(0, bccomp($fixed->finalRate, '0.9118', 18));
    }

    public function test_legacy_profit_fields_do_not_enter_formula(): void
    {
        $direction = $this->zelleDirection(floating: '-6', fix: '-3');
        $direction->profit = 12;
        $direction->add_course1 = 5;
        $direction->add_course2 = 5;
        $direction->course_value = '0.5'; // stale; override base proves fee path only

        $calc = CanonicalDirectionRateCalculator::make();
        $float = $calc->calculate($direction, RateMode::Floating, RateChannel::Website, '1');
        $fixed = $calc->calculate($direction, RateMode::Fixed, RateChannel::Website, '1');
        $fee0 = $calc->calculate(
            $this->zelleDirection(floating: '0', fix: '0'),
            RateMode::Floating,
            RateChannel::Website,
            '1',
        );

        $this->assertSame(0, bccomp($fee0->finalRate, '1', 18));
        $this->assertSame(0, bccomp($float->finalRate, '0.94', 18));
        $this->assertSame(0, bccomp($fixed->finalRate, '0.9118', 18));
    }

    private function zelleDirection(string $floating, string $fix): DirectionExchange
    {
        $direction = new DirectionExchange();
        $direction->id = 1525;
        $direction->id_currency1 = ZelleUsdBenchmarkResolver::ZELLE_CURRENCY_ID;
        $direction->is_type_rate = 1;
        $direction->floating_fee = $floating;
        $direction->fix_fee = $fix;
        $direction->profit = 0;
        $direction->add_course1 = 0;
        $direction->add_course2 = 0;

        return $direction;
    }
}
