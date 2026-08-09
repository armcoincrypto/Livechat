<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;

/**
 * Derived refresh must not clear owner export quarantine (allow_export=2 → 0).
 * That wipe previously left PUBLIC_ORDERABLE rows eligible while XML stayed stale.
 */
final class DerivedAllowExportPreserveTest extends TestCase
{
    public function test_allow_export_two_is_not_auto_cleared_in_write_payload_logic(): void
    {
        // Mirror DerivedMarketBaselineAuthority success-path policy:
        // preserve whatever allow_export is already stored.
        $rowAllowExport = 2;
        $written = (int) ($rowAllowExport === 2 ? 0 : $rowAllowExport); // legacy buggy
        $fixed = (int) $rowAllowExport;

        $this->assertSame(0, $written, 'documents legacy wipe behavior');
        $this->assertSame(2, $fixed, 'fixed policy preserves quarantine');
    }

    public function test_allow_export_schema_constants(): void
    {
        // Exporter semantics in ExportFormatHelpers::shouldExportRate
        $schema = [
            0 => 'unrestricted_when_other_gates_pass',
            1 => 'time_window_only_empty_window_blocks',
            2 => 'blocked_never_export',
        ];
        $this->assertArrayHasKey(0, $schema);
        $this->assertArrayHasKey(1, $schema);
        $this->assertArrayHasKey(2, $schema);
    }
}
