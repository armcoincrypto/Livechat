<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Models\DirectionExchange;
use App\Services\Rates\RateDirectionEligibility;
use Tests\TestCase;

/**
 * F1/F2 regression: enabled direction rows must not be quoteable, selectable
 * as a default/random pair, or order-eligible when either referenced currency
 * is hidden (status=1) or removed (status=2) on the currencies table.
 *
 * @group rates
 * @group operations
 */
final class DirectionQuoteableScopeTest extends TestCase
{
    public function testQuoteableScopeExcludesDirectionsReferencingAHiddenCurrency(): void
    {
        $hiddenCurrencyCode = DirectionExchange::query()
            ->where('direction_exchange.status', 1)
            ->join('currencies as c1', 'c1.id', '=', 'direction_exchange.id_currency1')
            ->where('c1.status', '!=', 0)
            ->value('c1.designation_xml');

        if ($hiddenCurrencyCode === null) {
            $this->markTestSkipped('No enabled direction referencing a hidden/removed currency in this environment.');
        }

        $stillReturnedByQuoteable = DirectionExchange::query()
            ->quoteable()
            ->whereHas('currency1', fn ($q) => $q->where('designation_xml', $hiddenCurrencyCode))
            ->exists();

        $this->assertFalse(
            $stillReturnedByQuoteable,
            "scopeQuoteable() must exclude directions referencing hidden currency {$hiddenCurrencyCode}"
        );
    }

    public function testActiveDirectionScopeNeverReturnsAHiddenCurrencyForAnyPair(): void
    {
        $direction = DirectionExchange::query()
            ->where('direction_exchange.status', 1)
            ->join('currencies as c2', 'c2.id', '=', 'direction_exchange.id_currency2')
            ->where('c2.status', '!=', 0)
            ->select('direction_exchange.*')
            ->first();

        if ($direction === null) {
            $this->markTestSkipped('No enabled direction referencing a hidden/removed destination currency in this environment.');
        }

        $direction->loadMissing(['currency1', 'currency2']);
        $from = $direction->currency1->designation_xml;
        $to = $direction->currency2->designation_xml;

        $result = DirectionExchange::activeDirection($from, $to)->first();

        $this->assertNull(
            $result,
            "activeDirection({$from}, {$to}) must not resolve a direction whose destination currency is hidden/removed"
        );
    }

    public function testRandomFallbackNeverReturnsAHiddenCurrencyDirection(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $item = DirectionExchange::activeDirection()->inRandomOrder()->first();

            if ($item === null) {
                continue;
            }

            $item->loadMissing(['currency1', 'currency2']);

            $this->assertSame(0, (int) $item->currency1->status, 'random fallback returned a hidden/removed source currency');
            $this->assertSame(0, (int) $item->currency2->status, 'random fallback returned a hidden/removed destination currency');
        }
    }

    public function testOrderCreationGateRejectsDirectionWithHiddenCurrency(): void
    {
        // Prefer a hidden-currency direction outside owner-retired TUSDTRC20/DAI (ids 43/41)
        // so this asserts the generic hidden-currency gate rather than C3-B retirement.
        $direction = DirectionExchange::query()
            ->where('status', 1)
            ->where(function ($q) {
                $q->whereHas('currency1', fn ($qq) => $qq->where('status', '!=', 0)->whereNotIn('id', [41, 43]))
                    ->orWhereHas('currency2', fn ($qq) => $qq->where('status', '!=', 0)->whereNotIn('id', [41, 43]));
            })
            ->where('id_currency1', '!=', 41)->where('id_currency2', '!=', 41)
            ->where('id_currency1', '!=', 43)->where('id_currency2', '!=', 43)
            ->first();

        if ($direction === null) {
            $this->markTestSkipped('No enabled direction referencing a non-retired hidden/removed currency in this environment.');
        }

        $result = RateDirectionEligibility::make()->evaluateDirection($direction);

        $this->assertFalse($result['order_allowed'], 'order creation must be blocked when either currency is hidden/removed');
        $this->assertFalse($result['quote_allowed'], 'quoting must be blocked when either currency is hidden/removed');
        $this->assertContains('currency_hidden_or_removed', $result['reasons']);
    }
}
