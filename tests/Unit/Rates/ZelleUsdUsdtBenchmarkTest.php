<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Models\DirectionExchange;
use App\Services\Rates\CanonicalDirectionRateCalculator;
use App\Services\Rates\PublicDuplicateExclusion;
use App\Services\Rates\RateChannel;
use App\Services\Rates\RateFeePercentNormalizer;
use App\Services\Rates\RateMode;
use App\Services\Rates\ZelleUsdBenchmarkResolver;
use App\Services\Rates\ZelleUsdBenchmarkResult;
use PHPUnit\Framework\TestCase;

final class ZelleUsdUsdtBenchmarkTest extends TestCase
{
    public function test_minus_six_percent_is_times_0_94(): void
    {
        $n = new RateFeePercentNormalizer();
        $this->assertSame(0, bccomp($n->applyPercentToRate('1', '-6', 18), '0.94', 18));
        $this->assertSame(0, bccomp($n->applyPercentToRate('100', '-6', 18), '94', 18));
    }

    public function test_minus_three_percent_is_times_0_97(): void
    {
        $n = new RateFeePercentNormalizer();
        $this->assertSame(0, bccomp($n->applyPercentToRate('1', '-3', 18), '0.97', 18));
    }

    public function test_percentage_applied_once_via_calculator(): void
    {
        $direction = new DirectionExchange();
        $direction->id = 1525;
        $direction->id_currency1 = 99; // not ZELLE — force override path only
        $direction->is_type_rate = 1;
        $direction->floating_fee = '-6';
        $direction->fix_fee = '-3';

        $calc = CanonicalDirectionRateCalculator::make();
        $float = $calc->calculate($direction, RateMode::Floating, RateChannel::Website, '1');
        $fixed = $calc->calculate($direction, RateMode::Fixed, RateChannel::Website, '1');
        $export = $calc->calculateForExport($direction, '1');

        $this->assertSame(0, bccomp($float->finalRate, '0.94', 18));
        $this->assertSame(0, bccomp($fixed->finalRate, '0.97', 18));
        $this->assertSame(0, bccomp($export->finalRate, '0.94', 18));
        // Not double-applied (-6 then -6 ⇒ 0.8836)
        $this->assertNotSame(0, bccomp($float->finalRate, '0.8836', 18));
    }

    public function test_website_payload_ignores_facade_override_base(): void
    {
        $direction = new DirectionExchange();
        $direction->id = 1525;
        $direction->id_currency1 = 99;
        $direction->is_type_rate = 1;
        $direction->floating_fee = '-6';
        $direction->fix_fee = '-3';

        $calc = CanonicalDirectionRateCalculator::make();
        // Simulates the old DirectionDetailResource bug: passing CalculatorFacade rate
        // that already included fees as if it were the bare base.
        $poisoned = $calc->websiteCoursePayload($direction, '0.864150943213026415');
        $this->assertSame(0, bccomp((string) $poisoned['floating_rate'], '0.812301886620244830', 18));

        $healthy = $calc->websiteCoursePayload($direction, '1');
        $this->assertSame(0, bccomp((string) $healthy['base_rate'], '1', 18));
        $this->assertSame(0, bccomp((string) $healthy['floating_rate'], '0.94', 18));
        // Non-ZELLE id_currency1=99: FIXED remains fix_fee alone.
        $this->assertSame(0, bccomp((string) $healthy['fixed_rate'], '0.97', 18));
    }

    public function test_non_zelle_fixed_still_numerically_larger_with_less_negative_fix(): void
    {
        // Non-ZELLE independent fees: floating=-6 ⇒ 94, fix=-3 ⇒ 97 (legacy semantics).
        $direction = new DirectionExchange();
        $direction->id = 1;
        $direction->id_currency1 = 99;
        $direction->is_type_rate = 1;
        $direction->floating_fee = '-6';
        $direction->fix_fee = '-3';
        $calc = CanonicalDirectionRateCalculator::make();
        $payload = $calc->websiteCoursePayload($direction, '100');
        $this->assertSame(0, bccomp((string) $payload['floating_rate'], '94', 18));
        $this->assertSame(0, bccomp((string) $payload['fixed_rate'], '97', 18));
        $this->assertTrue(bccomp((string) $payload['fixed_rate'], (string) $payload['floating_rate'], 18) > 0);
    }

    public function test_zelle_compounded_fixed_is_worse_than_floating(): void
    {
        $direction = new DirectionExchange();
        $direction->id = 1525;
        $direction->id_currency1 = ZelleUsdBenchmarkResolver::ZELLE_CURRENCY_ID;
        $direction->is_type_rate = 1;
        $direction->floating_fee = '-6';
        $direction->fix_fee = '-3';
        $calc = CanonicalDirectionRateCalculator::make();
        $payload = $calc->websiteCoursePayload($direction, '1');
        $this->assertSame(0, bccomp((string) $payload['floating_rate'], '0.94', 18));
        $this->assertSame(0, bccomp((string) $payload['fixed_rate'], '0.9118', 18));
        $this->assertTrue(bccomp((string) $payload['fixed_rate'], (string) $payload['floating_rate'], 18) < 0);
    }

    public function test_admin_fee_change_from_minus6_to_minus3_recalculates(): void
    {
        $direction = new DirectionExchange();
        $direction->id = 1540;
        $direction->id_currency1 = 99;
        $direction->is_type_rate = 1;
        $direction->floating_fee = '-6';
        $direction->fix_fee = '0';
        $calc = CanonicalDirectionRateCalculator::make();
        $bench = '80';

        $a = $calc->calculate($direction, RateMode::Floating, RateChannel::Website, $bench);
        $this->assertSame(0, bccomp($a->finalRate, '75.2', 18)); // 80*0.94

        $direction->floating_fee = '-3';
        $b = $calc->calculate($direction, RateMode::Floating, RateChannel::Website, $bench);
        $this->assertSame(0, bccomp($b->finalRate, '77.6', 18)); // 80*0.97
    }

    public function test_destination_variants_require_exact_currency_ids(): void
    {
        // Resolver match method is currency_id_exact — CARDAMD ≠ CASHAMD by construction.
        $this->assertSame(87, ZelleUsdBenchmarkResolver::ZELLE_CURRENCY_ID);
        $this->assertSame(3, ZelleUsdBenchmarkResolver::USDTTRC20_CURRENCY_ID);
        $this->assertNotSame(43, ZelleUsdBenchmarkResolver::USDTTRC20_CURRENCY_ID); // TUSDTRC20
    }

    public function test_reason_codes_are_stable(): void
    {
        $this->assertSame('ZELLE_BENCHMARK_FOUND', ZelleUsdBenchmarkResult::REASON_FOUND);
        $this->assertSame('ZELLE_BENCHMARK_MISSING', ZelleUsdBenchmarkResult::REASON_MISSING);
        $this->assertSame('ZELLE_BENCHMARK_AMBIGUOUS', ZelleUsdBenchmarkResult::REASON_AMBIGUOUS);
        $this->assertSame('ZELLE_BENCHMARK_ZERO', ZelleUsdBenchmarkResult::REASON_ZERO);
        $this->assertSame('ZELLE_BENCHMARK_STALE', ZelleUsdBenchmarkResult::REASON_STALE);
        $this->assertSame('ZELLE_BENCHMARK_NOT_QUOTEABLE', ZelleUsdBenchmarkResult::REASON_NOT_QUOTEABLE);
    }

    public function test_exclusions_keep_1971_not_rub_banks_or_stablecoin_twins(): void
    {
        PublicDuplicateExclusion::clearCache();
        $path = dirname(__DIR__, 3).'/resources/rates/public-duplicate-exclusions.json';
        $json = json_decode((string) file_get_contents($path), true);
        $ids = array_map('intval', $json['exclude_direction_ids'] ?? []);
        $this->assertSame(9, (int) ($json['version'] ?? 0));
        $this->assertNotContains(1519, $ids);
        $this->assertFalse(\App\Services\Rates\PublicDuplicateExclusion::isExcluded(1519));
        $this->assertContains(1971, $ids);
        foreach ([1540, 1541, 1542] as $id) {
            $this->assertNotContains($id, $ids, "ZELLE RUB bank {$id} must not remain excluded");
        }
        foreach ([1537, 1552, 1556, 1564, 1586, 1619] as $id) {
            $this->assertNotContains($id, $ids, "stablecoin ZELLE {$id} must not remain excluded");
        }
    }

    public function test_crypto_fiat_stable_fee_math_matrix(): void
    {
        $calc = CanonicalDirectionRateCalculator::make();
        $d = new DirectionExchange();
        $d->id = 1;
        $d->id_currency1 = 99;
        $d->is_type_rate = 1;
        $d->floating_fee = '-6';
        $d->fix_fee = '-3';

        foreach (['0.000015', '78.95', '1', '424.5'] as $base) {
            $f = $calc->calculate($d, RateMode::Floating, RateChannel::Website, $base);
            $expected = (new RateFeePercentNormalizer())->applyPercentToRate($base, '-6', 18);
            $this->assertSame(0, bccomp($f->finalRate, $expected, 18), "base={$base}");
        }
    }
}
