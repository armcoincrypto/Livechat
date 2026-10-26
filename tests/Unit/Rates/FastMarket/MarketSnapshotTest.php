<?php

declare(strict_types=1);

namespace Tests\Unit\Rates\FastMarket;

use App\Services\Rates\FastMarket\MarketSnapshot;
use PHPUnit\Framework\TestCase;

final class MarketSnapshotTest extends TestCase
{
    public function test_round_trip_array_and_freshness_states(): void
    {
        $now = 1_700_000_000;
        $snap = new MarketSnapshot(
            source: MarketSnapshot::SOURCE_BESTCHANGE,
            snapshotId: 'bc_test',
            sourceTimestamp: $now,
            fetchedAt: $now,
            normalizedAt: $now,
            expiresAt: $now + 180,
            fingerprint: 'abc',
            ratesByPair: [
                '1-2' => [['changer' => 1, 'rate' => '2']],
            ],
            pairByDirectionExchangeId: [10 => '1-2'],
            bcIdByDirectionExchangeId: [10 => 99],
            externalRates: ['USD' => '1'],
            exchangersMap: [1 => 'Ex'],
            pairCount: 1,
            mappedDirectionCount: 1,
            fetchMs: 12,
            buildMs: 20,
        );

        $again = MarketSnapshot::fromArray($snap->toArray());
        $this->assertSame('bc_test', $again->snapshotId);
        $this->assertSame(['changer' => 1, 'rate' => '2'], $again->rawRowsForDirectionExchange(10)[0]);
        $this->assertSame('1-2', $again->pairKeyForDirectionExchange(10));

        $this->assertSame(MarketSnapshot::STATE_FRESH, $again->freshnessState($now + 30));
        $this->assertSame(MarketSnapshot::STATE_STALE_BUT_USABLE, $again->freshnessState($now + 90));
        $this->assertSame(MarketSnapshot::STATE_EXPIRED, $again->freshnessState($now + 200));
        $this->assertTrue($again->isUsable($now + 90));
        $this->assertFalse($again->isUsable($now + 200));
    }
}
