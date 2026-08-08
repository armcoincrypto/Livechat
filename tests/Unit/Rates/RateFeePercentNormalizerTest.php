<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\CanonicalDirectionRateCalculator;
use App\Services\Rates\RateChannel;
use App\Services\Rates\RateFeePercentNormalizer;
use App\Services\Rates\RateMode;
use App\Models\DirectionExchange;
use PHPUnit\Framework\TestCase;

final class RateFeePercentNormalizerTest extends TestCase
{
    private RateFeePercentNormalizer $n;

    protected function setUp(): void
    {
        parent::setUp();
        $this->n = new RateFeePercentNormalizer();
    }

    public function test_bare_minus_three_is_percent_not_absolute(): void
    {
        $r = $this->n->normalize('-3');
        $this->assertTrue($r['ok']);
        $this->assertSame('-3', $r['percent']);
        $this->assertSame('-3%', $r['expression']);
        $this->assertSame(RateFeePercentNormalizer::CLASS_VALID_NUMERIC_PERCENT, $r['classification']);
    }

    public function test_percent_suffix_accepted(): void
    {
        $r = $this->n->normalize('-3%');
        $this->assertTrue($r['ok']);
        $this->assertSame('-3', $r['percent']);
        $this->assertSame('-3%', $r['expression']);
        $this->assertSame(RateFeePercentNormalizer::CLASS_VALID_PERCENT_STRING, $r['classification']);
    }

    public function test_zero_and_null(): void
    {
        $this->assertSame('0', $this->n->normalize('0')['percent']);
        $this->assertSame('0', $this->n->normalize(null)['percent']);
        $this->assertSame('0', $this->n->normalize('')['percent']);
    }

    public function test_positive_and_decimal(): void
    {
        $this->assertSame('2.5', $this->n->normalize('2.5')['percent']);
        $this->assertSame('+2.5%', $this->n->normalize('+2.5')['expression']);
    }

    public function test_malformed_rejected(): void
    {
        $r = $this->n->normalize('*0.97');
        $this->assertFalse($r['ok']);
        $this->assertSame(RateFeePercentNormalizer::CLASS_MALFORMED, $r['classification']);
    }

    public function test_apply_percent_formula_matches_owner_example(): void
    {
        $final = $this->n->applyPercentToRate('44.154000007241256001', '-3', 18);
        $this->assertSame('42.829380007024018320', $final);
    }

    public function test_absolute_path_would_differ_from_percent(): void
    {
        $percent = $this->n->applyPercentToRate('44.154000007241256001', '-3', 18);
        $absolute = bcadd('44.154000007241256001', '-3', 18);
        $this->assertNotSame($percent, $absolute);
        $this->assertSame('41.154000007241256001', $absolute);
    }
}
