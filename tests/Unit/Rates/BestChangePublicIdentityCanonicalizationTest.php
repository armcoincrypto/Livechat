<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Models\DirectionExchange;
use iEXPackages\Courses\Export\Concerns\ExportFormatHelpers;
use PHPUnit\Framework\TestCase;

final class BestChangePublicIdentityCanonicalizationTest extends TestCase
{
    public function testOnlyFirstEligibleCardAmdVariantClaimsPublicPair(): void
    {
        $selector = $this->selector();
        $evoca = $this->direction(1001, 52);
        $agba = $this->direction(1002, 53);

        $this->assertTrue($selector->claim($evoca, 'USDTTRC20', 'CARDAMD'));
        $this->assertFalse($selector->claim($agba, 'USDTTRC20', 'CARDAMD'));
        $this->assertTrue($selector->claim($evoca, 'USDTTRC20', 'CARDAMD'));

        // Export selection is in-memory only; internal variants stay untouched.
        $this->assertSame(1, (int) $evoca->status);
        $this->assertSame(1, (int) $agba->status);
        $this->assertSame(52, (int) $evoca->id_currency2);
        $this->assertSame(53, (int) $agba->id_currency2);
    }

    public function testUnrelatedDuplicateCodeIsNotCollapsedByCardAmdRule(): void
    {
        $selector = $this->selector();

        $this->assertTrue($selector->claim($this->direction(2001, 201), 'BTC', 'SBPRUB'));
        $this->assertTrue($selector->claim($this->direction(2002, 202), 'BTC', 'SBPRUB'));
    }

    public function testNewExportPassCanSelectCanonicalCardAmdAgain(): void
    {
        $selector = $this->selector();
        $direction = $this->direction(3001, 52);

        $this->assertTrue($selector->claim($direction, 'USDTTRC20', 'CARDAMD'));
        $selector->reset();
        $this->assertTrue($selector->claim($direction, 'USDTTRC20', 'CARDAMD'));
    }

    private function selector(): object
    {
        return new class {
            use ExportFormatHelpers;

            public function claim(
                DirectionExchange $direction,
                string $from,
                string $to,
            ): bool {
                return $this->claimCanonicalPublicPair($direction, $from, $to);
            }

            public function reset(): void
            {
                $this->resetCanonicalPublicPairSelection();
            }
        };
    }

    private function direction(int $id, int $currencyOutId): DirectionExchange
    {
        $direction = new DirectionExchange();
        $direction->forceFill([
            'id' => $id,
            'id_currency1' => 10,
            'id_currency2' => $currencyOutId,
            'status' => 1,
            'allow_export' => 0,
            'course_value' => '1',
        ]);

        return $direction;
    }
}
