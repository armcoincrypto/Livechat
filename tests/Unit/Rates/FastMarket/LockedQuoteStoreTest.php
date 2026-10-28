<?php

declare(strict_types=1);

namespace Tests\Unit\Rates\FastMarket;

use App\Services\Rates\FastMarket\LockedQuoteException;
use App\Services\Rates\FastMarket\LockedQuoteRecord;
use App\Services\Rates\FastMarket\LockedQuoteStore;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use PHPUnit\Framework\TestCase;

final class LockedQuoteStoreTest extends TestCase
{
    private LockedQuoteStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new LockedQuoteStore(new Repository(new ArrayStore));
    }

    public function test_put_get_roundtrip(): void
    {
        $rec = $this->sampleRecord();
        $this->store->put($rec, 30);
        $got = $this->store->get($rec->quoteId);
        $this->assertInstanceOf(LockedQuoteRecord::class, $got);
        $this->assertSame($rec->courseDisplay, $got->courseDisplay);
        $this->assertSame($rec->rateValue, $got->rateValue);
    }

    public function test_unknown_token_returns_null(): void
    {
        $this->assertNull($this->store->get('missing-token-xyz'));
    }

    public function test_malformed_record_throws(): void
    {
        $repo = new Repository(new ArrayStore);
        $repo->put(LockedQuoteStore::KEY_PREFIX.'bad', ['quote_id' => 'bad'], 30);
        $store = new LockedQuoteStore($repo);
        $this->expectException(LockedQuoteException::class);
        $store->get('bad');
    }

    public function test_generation_bump_invalidates_policy_hook(): void
    {
        $this->assertSame(0, $this->store->currentGeneration());
        $next = $this->store->bumpGeneration();
        $this->assertSame(1, $next);
        $this->assertSame(1, $this->store->currentGeneration());
    }

    private function sampleRecord(): LockedQuoteRecord
    {
        $now = time();

        return new LockedQuoteRecord(
            quoteId: 'tok_'.bin2hex(random_bytes(8)),
            createdAt: $now,
            expiresAt: $now + 30,
            snapshotId: 'snap_1',
            snapshotFetchedAt: $now,
            directionId: 42,
            pricingFamily: 'BESTCHANGE',
            sourceOwner: 'BestChangeBookRateComputer+CalculatorFacade',
            inputBindingHash: hash('sha256', 'x'),
            canonicalInput: [
                'direction_id' => 42,
                'currency_in_id' => 1,
                'currency_out_id' => 2,
                'pricing_family' => 'BESTCHANGE',
                'source_owner' => 'BestChangeBookRateComputer+CalculatorFacade',
            ],
            rateValue: '1.2345',
            calculatorRate: '1.2345',
            courseDisplay: '1 USD = 1.2345 EUR',
            displayRate: '1.2345',
            pricingFingerprint: hash('sha256', 'fp'),
            generation: 0,
            status: LockedQuoteRecord::STATUS_ISSUED,
            usedFastPath: true,
        );
    }
}
