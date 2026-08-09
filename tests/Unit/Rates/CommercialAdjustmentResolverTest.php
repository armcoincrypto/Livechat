<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Models\DirectionExchange;
use App\Services\Rates\CommercialAdjustmentResolver;
use App\Services\Rates\ZelleUsdUsdtBenchmarkAuthority;
use PHPUnit\Framework\TestCase;

final class CommercialAdjustmentResolverTest extends TestCase
{
    public function test_zelle_display_maps_floating_fee_to_margin_profit(): void
    {
        $resolver = new CommercialAdjustmentResolver();
        $d = $this->direction([
            'parser_source_name' => ZelleUsdUsdtBenchmarkAuthority::PARSER_SOURCE_NAME,
            'id_currency1' => 87,
            'profit' => '0',
            'floating_fee' => '-6',
        ]);

        $this->assertSame('ZELLE', $resolver->family($d));
        $this->assertSame('floating_fee', $resolver->storageField($d));
        $this->assertSame('6', $resolver->displayProfitPercent($d));

        $payload = $resolver->buildUpdatePayload($d, '6');
        $this->assertSame('0', $payload['profit']);
        $this->assertSame('-6', $payload['floating_fee']);

        $payload = $resolver->buildUpdatePayload($d, '1.5');
        $this->assertSame('0', $payload['profit']);
        $this->assertSame('-1.5', $payload['floating_fee']);
    }

    public function test_derived_keeps_profit_canonical(): void
    {
        $resolver = new CommercialAdjustmentResolver();
        $d = $this->direction([
            'parser_source_name' => 'DERIVED_MARKET_BASELINE',
            'profit' => '1.5',
            'floating_fee' => '0',
        ]);

        $this->assertSame('DERIVED', $resolver->family($d));
        $this->assertSame('profit', $resolver->storageField($d));
        $this->assertSame('1.5', $resolver->displayProfitPercent($d));

        $payload = $resolver->buildUpdatePayload($d, '3');
        $this->assertSame('3', $payload['profit']);
        $this->assertSame('0', $payload['floating_fee']);
        $this->assertSame(1, $payload['is_type_rate']);
    }

    public function test_bestchange_maps_to_profit_without_touching_floating_fee(): void
    {
        $resolver = new CommercialAdjustmentResolver();
        $d = $this->direction([
            'parser_source_name' => 'BestChange',
            'profit' => '0.5',
            'floating_fee' => '0',
        ]);

        $this->assertSame('BESTCHANGE', $resolver->family($d));
        $payload = $resolver->buildUpdatePayload($d, '1.5');
        $this->assertSame('1.5', $payload['profit']);
        $this->assertArrayNotHasKey('floating_fee', $payload);
    }

    /** @param array<string, mixed> $attrs */
    private function direction(array $attrs): DirectionExchange
    {
        $d = new DirectionExchange();
        foreach ($attrs as $k => $v) {
            $d->{$k} = $v;
        }

        return $d;
    }
}
