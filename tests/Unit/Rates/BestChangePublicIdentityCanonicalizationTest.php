<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Models\DirectionExchange;
use iEXPackages\Courses\Export\Concerns\ExportFormatHelpers;
use PHPUnit\Framework\TestCase;

final class BestChangePublicIdentityCanonicalizationTest extends TestCase
{
    public function testOnlyExplicitlyApprovedCardAmdDirectionClaimsPublicPair(): void
    {
        $selector = $this->selector();
        $evoca = $this->direction(81, 52);
        $ineco = $this->direction(83, 58);

        $this->assertTrue($selector->claim($evoca, 'USDTTRC20', 'CARDAMD'));
        $this->assertFalse($selector->claim($ineco, 'USDTTRC20', 'CARDAMD'));
        $this->assertTrue($selector->claim($evoca, 'USDTTRC20', 'CARDAMD'));

        // Export selection is in-memory only; internal variants stay untouched.
        $this->assertSame(1, (int) $evoca->status);
        $this->assertSame(1, (int) $ineco->status);
        $this->assertSame(52, (int) $evoca->id_currency2);
        $this->assertSame(58, (int) $ineco->id_currency2);
    }

    public function testUnrelatedDuplicateCodeIsNotCollapsedByCardAmdRule(): void
    {
        $selector = $this->selector();

        $this->assertTrue($selector->claim($this->direction(2001, 201), 'BTC', 'SBPRUB'));
        $this->assertTrue($selector->claim($this->direction(2002, 202), 'BTC', 'SBPRUB'));
    }

    public function testUnconfiguredCardAmdPairFailsClosed(): void
    {
        $selector = $this->selector();

        $this->assertFalse($selector->claim($this->direction(3001, 52), 'NEWCOIN', 'CARDAMD'));
    }

    public function testNewExportPassKeepsSameApprovedDirection(): void
    {
        $selector = $this->selector();
        $direction = $this->direction(81, 52);

        $this->assertTrue($selector->claim($direction, 'USDTTRC20', 'CARDAMD'));
        $selector->reset();
        $this->assertTrue($selector->claim($direction, 'USDTTRC20', 'CARDAMD'));
    }

    public function testVersionedConfigurationContainsThirteenApprovedPairs(): void
    {
        $config = json_decode(
            (string) file_get_contents($this->configPath()),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $pairs = $config['identities']['CARDAMD']['direction_ids_by_pair'];

        $this->assertCount(13, $pairs);
        $this->assertSame(81, $pairs['USDTTRC20->CARDAMD']);
        $this->assertSame(1018, $pairs['BNBBEP20->CARDAMD']);
        $this->assertSame(5, $config['identities']['CARDAMD']['bestchange_currency_id']);
        $this->assertSame([52, 53, 54, 55, 56, 57, 58, 71], $config['identities']['CARDAMD']['internal_currency_ids']);
    }

    private function selector(): object
    {
        return new class($this->configPath()) {
            use ExportFormatHelpers;

            public function __construct(private readonly string $configPath)
            {
            }

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

            protected function canonicalPublicDirectionsPath(): string
            {
                return $this->configPath;
            }
        };
    }

    private function configPath(): string
    {
        return dirname(__DIR__, 3) . '/resources/rates/bestchange-public-directions.json';
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
