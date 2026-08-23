<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\CanonicalDirectionRateCalculator;
use App\Services\Rates\RateChannel;
use App\Services\Rates\RateMode;
use App\Models\DirectionExchange;
use PHPUnit\Framework\TestCase;

final class CanonicalDirectionRateCalculatorTest extends TestCase
{
    public function test_floating_is_default_and_xml_exports_floating(): void
    {
        $direction = new DirectionExchange();
        $direction->id = 31;
        $direction->is_type_rate = 1;
        $direction->fix_fee = '-3';
        $direction->floating_fee = '0';
        $direction->file_rate_source = 'fix'; // historical gate value; must not select fix_fee
        $direction->course_value = '44.154000007241256001';

        $base = '44.154000007241256001';
        $calc = CanonicalDirectionRateCalculator::make();
        $fixed = $calc->calculate($direction, RateMode::Fixed, RateChannel::Website, $base);
        $float = $calc->calculate($direction, RateMode::Floating, RateChannel::Website, $base);
        $export = $calc->calculateForExport($direction, $base);

        $this->assertSame('42.829380007024018320', $fixed->finalRate);
        $this->assertSame('44.154000007241256001', $float->finalRate);
        $this->assertSame($float->finalRate, $export->finalRate);
        $this->assertSame(RateMode::Floating, $export->mode);
        $this->assertSame(RateMode::Floating, CanonicalDirectionRateCalculator::DEFAULT_MODE);

        $payload = $calc->websiteCoursePayload($direction, $base);
        $this->assertSame('floating', $payload['default_mode']);
        $this->assertSame($payload['floating_rate'], $payload['display_rate']);
        $this->assertSame($payload['floating_rate'], $payload['rate']);
        $this->assertSame('percent', $payload['fee']['floating']['unit']);
        $this->assertSame('0', $payload['fee']['floating']['value']);
        $this->assertSame('-3', $payload['fee']['fixed']['value']);
    }

    public function test_xml_never_uses_fix_fee_even_when_file_rate_source_is_fix(): void
    {
        $direction = new DirectionExchange();
        $direction->id = 99;
        $direction->is_type_rate = 1;
        $direction->fix_fee = '-3';
        $direction->floating_fee = '0';
        $direction->file_rate_source = 'fix';

        $export = CanonicalDirectionRateCalculator::make()
            ->calculateForExport($direction, '100');

        $this->assertSame(0, bccomp($export->finalRate, '100', 18));
        $this->assertSame(RateMode::Floating, $export->mode);
        // Must not equal fixed (-3%) result of 97.
        $this->assertNotSame(0, bccomp($export->finalRate, '97', 18));
    }

    public function test_nonzero_floating_fee_percent_applies_to_xml(): void
    {
        $direction = new DirectionExchange();
        $direction->id = 100;
        $direction->is_type_rate = 1;
        $direction->fix_fee = '-3';
        $direction->floating_fee = '-1';
        $direction->file_rate_source = 'fix';

        $export = CanonicalDirectionRateCalculator::make()
            ->calculateForExport($direction, '100');

        $this->assertSame(0, bccomp($export->finalRate, '99', 18));
        $this->assertSame(RateMode::Floating, $export->mode);
    }

    public function test_percent_expressions_for_calculator(): void
    {
        $direction = new DirectionExchange();
        $direction->fix_fee = '-3';
        $direction->floating_fee = '0';
        $expr = CanonicalDirectionRateCalculator::make()->percentExpressionsForCalculator($direction);
        $this->assertSame('-3%', $expr['fixed']);
        $this->assertSame('0%', $expr['floating']);
    }

    public function test_explicit_fixed_mode_remains_fixed(): void
    {
        $direction = new DirectionExchange();
        $direction->id = 31;
        $direction->is_type_rate = 1;
        $direction->fix_fee = '-3';
        $direction->floating_fee = '0';

        $fixed = CanonicalDirectionRateCalculator::make()
            ->calculate($direction, RateMode::Fixed, RateChannel::Order, '100');

        $this->assertSame(0, bccomp($fixed->finalRate, '97', 18));
        $this->assertSame(RateMode::Fixed, $fixed->mode);
    }

    public function test_derived_profit_margin_semantics_map_to_floating_fee(): void
    {
        $direction = new DirectionExchange();
        $direction->id = 419;
        $direction->is_type_rate = 1;
        $direction->parser_source_name = 'DERIVED_MARKET_BASELINE';
        $direction->floating_fee = '0';
        $direction->fix_fee = '0';
        $direction->profit = '5'; // more margin → fee -5%

        $calc = CanonicalDirectionRateCalculator::make();
        $this->assertSame(0, bccomp($calc->resolveFloatingFeePercent($direction), '-5', 8));

        $export = $calc->calculateForExport($direction, '100');
        $this->assertSame(0, bccomp($export->finalRate, '95', 18));

        $direction->profit = '-5'; // more competitive → fee +5%
        $this->assertSame(0, bccomp($calc->resolveFloatingFeePercent($direction), '5', 8));
        $exportUp = $calc->calculateForExport($direction, '100');
        $this->assertSame(0, bccomp($exportUp->finalRate, '105', 18));
    }

    public function test_bestchange_profit_is_not_reapplied_by_canonical(): void
    {
        $direction = new DirectionExchange();
        $direction->id = 3219;
        $direction->is_type_rate = 1;
        $direction->parser_source_name = 'BestChange';
        $direction->floating_fee = '0';
        $direction->fix_fee = '0';
        $direction->profit = '1';
        $direction->course_value = '98.73214659753115979';

        $calc = CanonicalDirectionRateCalculator::make();
        $this->assertSame(0, bccomp($calc->resolveFloatingFeePercent($direction), '0', 8));

        $export = $calc->calculateForExport($direction, '98.73214659753115979');
        $this->assertSame(0, bccomp($export->finalRate, '98.73214659753115979', 18));
    }

    public function test_derived_applies_admin_profit_exactly_once_on_raw_base(): void
    {
        $direction = new DirectionExchange();
        $direction->id = 419;
        $direction->is_type_rate = 1;
        $direction->parser_source_name = 'DERIVED_MARKET_BASELINE';
        $direction->floating_fee = '0';
        $direction->fix_fee = '0';
        $direction->profit = '1';

        $calc = CanonicalDirectionRateCalculator::make();
        $this->assertSame(0, bccomp($calc->resolveFloatingFeePercent($direction), '-1', 8));
        $export = $calc->calculateForExport($direction, '100');
        $this->assertSame(0, bccomp($export->finalRate, '99', 18));
    }

    public function test_derived_profit_zero_falls_back_to_floating_fee(): void
    {
        $direction = new DirectionExchange();
        $direction->id = 419;
        $direction->parser_source_name = 'DERIVED_MARKET_BASELINE';
        $direction->profit = '0';
        $direction->floating_fee = '-6';

        $this->assertSame(
            '-6',
            CanonicalDirectionRateCalculator::make()->resolveFloatingFeePercent($direction)
        );
    }
}
