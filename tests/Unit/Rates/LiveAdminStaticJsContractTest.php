<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;

/**
 * Locks the live admin JS snowflakes that 494726ed preserved.
 * The frontend ops-next public/static/ path is stale; source of truth is backend public/static.
 */
final class LiveAdminStaticJsContractTest extends TestCase
{
    public function test_mapping_labels_demote_inactive_historical_errors(): void
    {
        $js = $this->readPublicStatic('admin-bestchange-mapping-labels.js');
        $this->assertStringContainsString('ACTIVE ERROR', $js);
        $this->assertStringContainsString('INACTIVE HISTORICAL ERROR', $js);
        $this->assertStringContainsString('visual', $js);
    }

    public function test_ownership_panel_surfaces_inactive_authority_label(): void
    {
        $js = $this->readPublicStatic('admin-pricing-ownership-panel.js');
        $this->assertStringContainsString('exs-pricing-ownership-panel', $js);
        $this->assertStringContainsString('rate_source_label', $js);
        $this->assertStringContainsString('Источник курса', $js);
    }

    private function readPublicStatic(string $name): string
    {
        $path = dirname(__DIR__, 3).'/public/static/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
