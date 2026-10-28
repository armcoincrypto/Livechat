<?php

declare(strict_types=1);

namespace Tests\Unit\Rates\FastMarket;

use App\Models\DirectionExchange;
use App\Services\Rates\FastMarket\FastMarketFlags;
use App\Services\Rates\FastMarket\FastMarketSourceRouter;
use App\Services\Rates\FastMarket\LockedQuoteException;
use App\Services\Rates\FastMarket\LockedQuoteRecord;
use App\Services\Rates\FastMarket\LockedQuoteService;
use App\Services\Rates\FastMarket\LockedQuoteStore;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

final class LockedQuoteServiceTest extends TestCase
{
    private LockedQuoteStore $store;

    private LockedQuoteService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $app = new Container;
        Container::setInstance($app);
        $app->instance('config', new ConfigRepository([
            'fast_market' => [
                'plane_enabled' => true,
                'public_quote_enabled' => true,
                'order_quote_enabled' => false,
                'locked_quote_enabled' => true,
                'order_quote_shadow_enabled' => false,
                'shadow_enabled' => false,
                'quote_ttl_seconds' => 30,
                'fresh_seconds' => 60,
                'grace_seconds' => 120,
                'expired_seconds' => 180,
                'max_fallback_rate_age_seconds' => 1200,
                'snapshot_refresh_seconds' => 30,
                'producer_daemon' => true,
                'scheduler_fallback' => false,
                'producer_lock_seconds' => 55,
                'failure_backoff_seconds' => 15,
                'failure_backoff_max_seconds' => 60,
            ],
        ]));
        $this->store = new LockedQuoteStore(new Repository(new ArrayStore));
        $router = $this->createMock(FastMarketSourceRouter::class);
        $router->method('route')->willReturn([
            'family' => FastMarketSourceRouter::FAMILY_BESTCHANGE,
            'fast_source_available' => true,
            'target_refresh' => '30-60s_snapshot',
            'calculation_owner' => 'BestChangeBookRateComputer+CalculatorFacade',
            'fallback' => 'persisted',
            'use_bestchange_snapshot' => true,
        ]);
        $this->service = new LockedQuoteService($this->store, $router);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        parent::tearDown();
    }

    public function test_issue_requires_fast_path(): void
    {
        $dir = $this->fakeDirection(10, 1, 2);
        $meta = $this->service->issueFromFastQuote($dir, [
            'used_fast_path' => false,
            'calculator_payload' => ['rate' => '1.1'],
        ]);
        $this->assertNull($meta);
    }

    public function test_issue_and_resolve_exact_price(): void
    {
        $this->assertTrue(FastMarketFlags::lockedQuoteEnabled());
        $dir = $this->fakeDirection(10, 1, 2);
        $meta = $this->service->issueFromFastQuote($dir, [
            'used_fast_path' => true,
            'snapshot_id' => 'snap_abc',
            'snapshot_fetched_at' => time(),
            'market_age_seconds' => 5,
            'freshness_state' => 'FRESH',
            'rate_value' => '0.00042',
            'rate' => '0.00042',
            'course_display' => '1 BTC = 0.00042 ETH',
            'display_rate' => '0.00042',
            'calculator_payload' => ['rate' => '0.00042'],
        ]);
        $this->assertIsArray($meta);
        $this->assertArrayHasKey('quote_token', $meta);
        $this->assertSame(30, $meta['valid_for_seconds']);

        $resolved = $this->service->resolveForOrder($meta['quote_token'], [
            'direction_id' => 10,
            'currency_in_id' => 1,
            'currency_out_id' => 2,
        ]);
        $this->assertSame('1 BTC = 0.00042 ETH', $resolved->courseDisplay);
        $this->assertSame('0.00042', $resolved->rateValue);
    }

    public function test_expired_token_rejected(): void
    {
        $rec = $this->putExpired();
        $this->expectException(LockedQuoteException::class);
        try {
            $this->service->resolveForOrder($rec->quoteId, [
                'direction_id' => 10,
                'currency_in_id' => 1,
                'currency_out_id' => 2,
            ], time());
        } catch (LockedQuoteException $e) {
            $this->assertSame(LockedQuoteException::EXPIRED, $e->errorCode);
            throw $e;
        }
    }

    public function test_wrong_direction_blocked(): void
    {
        $dir = $this->fakeDirection(10, 1, 2);
        $meta = $this->service->issueFromFastQuote($dir, [
            'used_fast_path' => true,
            'snapshot_id' => 's',
            'rate_value' => '1.5',
            'rate' => '1.5',
            'course_display' => '1.5',
            'display_rate' => '1.5',
            'calculator_payload' => ['rate' => '1.5'],
        ]);
        $this->assertIsArray($meta);
        $this->expectException(LockedQuoteException::class);
        try {
            $this->service->resolveForOrder($meta['quote_token'], [
                'direction_id' => 99,
                'currency_in_id' => 1,
                'currency_out_id' => 2,
            ]);
        } catch (LockedQuoteException $e) {
            $this->assertSame(LockedQuoteException::INPUT_MISMATCH, $e->errorCode);
            throw $e;
        }
    }

    public function test_wrong_currency_blocked(): void
    {
        $dir = $this->fakeDirection(10, 1, 2);
        $meta = $this->service->issueFromFastQuote($dir, [
            'used_fast_path' => true,
            'snapshot_id' => 's',
            'rate_value' => '1.5',
            'rate' => '1.5',
            'course_display' => '1.5',
            'display_rate' => '1.5',
            'calculator_payload' => ['rate' => '1.5'],
        ]);
        $this->expectException(LockedQuoteException::class);
        $this->service->resolveForOrder($meta['quote_token'], [
            'direction_id' => 10,
            'currency_in_id' => 1,
            'currency_out_id' => 999,
        ]);
    }

    public function test_unknown_token(): void
    {
        $this->expectException(LockedQuoteException::class);
        try {
            $this->service->resolveForOrder('notarealtoken0123456789abcdef', [
                'direction_id' => 10,
                'currency_in_id' => 1,
                'currency_out_id' => 2,
            ]);
        } catch (LockedQuoteException $e) {
            $this->assertSame(LockedQuoteException::NOT_FOUND, $e->errorCode);
            throw $e;
        }
    }

    public function test_generation_invalidation(): void
    {
        $dir = $this->fakeDirection(10, 1, 2);
        $meta = $this->service->issueFromFastQuote($dir, [
            'used_fast_path' => true,
            'snapshot_id' => 's',
            'rate_value' => '2',
            'rate' => '2',
            'course_display' => '2',
            'display_rate' => '2',
            'calculator_payload' => ['rate' => '2'],
        ]);
        $this->store->bumpGeneration();
        $this->expectException(LockedQuoteException::class);
        try {
            $this->service->resolveForOrder($meta['quote_token'], [
                'direction_id' => 10,
                'currency_in_id' => 1,
                'currency_out_id' => 2,
            ]);
        } catch (LockedQuoteException $e) {
            $this->assertSame(LockedQuoteException::INVALIDATED, $e->errorCode);
            throw $e;
        }
    }

    public function test_exact_expiry_boundary(): void
    {
        $now = 1_700_000_000;
        $rec = new LockedQuoteRecord(
            quoteId: 'boundarytok01234567890123456789012',
            createdAt: $now - 30,
            expiresAt: $now,
            snapshotId: 's',
            snapshotFetchedAt: $now - 30,
            directionId: 10,
            pricingFamily: 'BESTCHANGE',
            sourceOwner: 'x',
            inputBindingHash: 'h',
            canonicalInput: [
                'direction_id' => 10,
                'currency_in_id' => 1,
                'currency_out_id' => 2,
                'pricing_family' => 'BESTCHANGE',
                'source_owner' => 'x',
            ],
            rateValue: '1',
            calculatorRate: '1',
            courseDisplay: '1',
            displayRate: '1',
            pricingFingerprint: 'fp',
            generation: 0,
            status: LockedQuoteRecord::STATUS_ISSUED,
            usedFastPath: true,
        );
        $this->store->put($rec, 30);
        $this->expectException(LockedQuoteException::class);
        $this->service->resolveForOrder($rec->quoteId, [
            'direction_id' => 10,
            'currency_in_id' => 1,
            'currency_out_id' => 2,
        ], $now);
    }

    private function putExpired(): LockedQuoteRecord
    {
        $now = time();
        $rec = new LockedQuoteRecord(
            quoteId: 'expiredtok0123456789012345678901',
            createdAt: $now - 120,
            expiresAt: $now - 60,
            snapshotId: 's',
            snapshotFetchedAt: $now - 120,
            directionId: 10,
            pricingFamily: 'BESTCHANGE',
            sourceOwner: 'x',
            inputBindingHash: 'h',
            canonicalInput: [
                'direction_id' => 10,
                'currency_in_id' => 1,
                'currency_out_id' => 2,
                'pricing_family' => 'BESTCHANGE',
                'source_owner' => 'x',
            ],
            rateValue: '1',
            calculatorRate: '1',
            courseDisplay: '1',
            displayRate: '1',
            pricingFingerprint: 'fp',
            generation: 0,
            status: LockedQuoteRecord::STATUS_ISSUED,
            usedFastPath: true,
        );
        $this->store->put($rec, 5);

        return $rec;
    }

    private function fakeDirection(int $id, int $in, int $out): DirectionExchange
    {
        $dir = new DirectionExchange;
        $dir->id = $id;
        $dir->id_currency1 = $in;
        $dir->id_currency2 = $out;
        $dir->profit = '0';
        $dir->add_course = '0';
        $dir->floating_fee = '0';
        $dir->fixed_fee = '0';

        return $dir;
    }
}
