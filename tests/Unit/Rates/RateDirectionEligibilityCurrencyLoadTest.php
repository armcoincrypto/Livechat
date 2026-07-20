<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Models\Currency;
use App\Models\DirectionExchange;
use App\Services\Rates\RateDirectionEligibility;
use Tests\TestCase;

/**
 * Regression: evaluateDirection must not poison currency relations with a
 * column-constrained loadMissing that strips id_payment (ops CurrencyResource 500).
 *
 * @group rates
 * @group operations
 */
final class RateDirectionEligibilityCurrencyLoadTest extends TestCase
{
    public function testEvaluateDirectionPreservesPaymentForeignKeyOnCurrencies(): void
    {
        $direction = DirectionExchange::query()
            ->where('status', 1)
            ->whereHas('currency1', fn ($q) => $q->where('status', 0)->where('designation_xml', 'CASHUSD'))
            ->whereHas('currency2', fn ($q) => $q->where('status', 0)->where('designation_xml', 'USDTTRC20'))
            ->first();

        if ($direction === null) {
            $this->markTestSkipped('CASHUSD→USDTTRC20 direction not present in this environment');
        }

        // Ensure relations start unloaded so loadMissing runs.
        $direction->unsetRelation('currency1');
        $direction->unsetRelation('currency2');

        RateDirectionEligibility::make()->evaluateDirection($direction);

        $c1 = $direction->currency1;
        $c2 = $direction->currency2;

        $this->assertInstanceOf(Currency::class, $c1);
        $this->assertInstanceOf(Currency::class, $c2);
        $this->assertNotNull($c1->id_payment, 'currency1.id_payment must survive evaluateDirection load');
        $this->assertNotNull($c2->id_payment, 'currency2.id_payment must survive evaluateDirection load');
        $this->assertNotNull($c1->payment, 'currency1.payment relation must resolve');
        $this->assertNotNull($c2->payment, 'currency2.payment relation must resolve');
        $this->assertNotEmpty((string) $c1->payment->logo);
        $this->assertNotEmpty((string) $c2->payment->logo);
    }

    public function testColumnConstrainedCurrencyLoadPoisonsPaymentRelation(): void
    {
        $direction = DirectionExchange::query()
            ->where('status', 1)
            ->whereHas('currency1', fn ($q) => $q->where('status', 0)->where('designation_xml', 'BTC'))
            ->whereHas('currency2', fn ($q) => $q->where('status', 0)->where('designation_xml', 'USDTTRC20'))
            ->first();

        if ($direction === null) {
            $this->markTestSkipped('BTC→USDTTRC20 direction not present in this environment');
        }

        $direction->unsetRelation('currency1');
        $direction->unsetRelation('currency2');
        $direction->loadMissing(['currency1:id,designation_xml', 'currency2:id,designation_xml']);

        $this->assertNull($direction->currency2->payment, 'documents the partial-load hazard this fix removes');
    }
}
