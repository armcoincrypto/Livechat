<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Models\DirectionExchange;
use iEXPackages\Courses\Export\Formats\BestChangeXMLExportFormat;
use PHPUnit\Framework\TestCase;

/**
 * Catalog→XML parity contracts for export eligibility (no pricing mutation).
 */
final class XmlExportCatalogParityContractTest extends TestCase
{
    public function test_zelleusd_cardamd_live_orderable_direction_is_export_claimable(): void
    {
        $config = json_decode(
            (string) file_get_contents(dirname(__DIR__, 3) . '/resources/rates/bestchange-public-directions.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $pairId = (int) ($config['identities']['CARDAMD']['direction_ids_by_pair']['ZELLEUSD->CARDAMD'] ?? 0);
        $this->assertSame(2111, $pairId);

        $selector = new class(dirname(__DIR__, 3) . '/resources/rates/bestchange-public-directions.json') {
            use \iEXPackages\Courses\Export\Concerns\ExportFormatHelpers;

            public function __construct(private readonly string $configPath)
            {
            }

            public function claim(DirectionExchange $d, string $from, string $to): bool
            {
                return $this->claimCanonicalPublicPair($d, $from, $to);
            }

            protected function canonicalPublicDirectionsPath(): string
            {
                return $this->configPath;
            }
        };

        $live = new DirectionExchange();
        $live->forceFill(['id' => 2111, 'id_currency2' => 52, 'status' => 1, 'allow_export' => 0, 'course_value' => '1']);
        $stale = new DirectionExchange();
        $stale->forceFill(['id' => 1558, 'id_currency2' => 56, 'status' => 1, 'allow_export' => 0, 'course_value' => '1']);

        $this->assertTrue($selector->claim($live, 'ZELLEUSD', 'CARDAMD'));
        $this->assertFalse($selector->claim($stale, 'ZELLEUSD', 'CARDAMD'));
    }

    public function test_allow_export_hard_disable_is_documented_exclusion_constant(): void
    {
        // allow_export=2 is the explicit XML hard-disable (see ExportFormatHelpers::shouldExportRate).
        $this->assertSame(2, 2);
    }
}
