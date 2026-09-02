<?php

declare(strict_types=1);

namespace Tests\Unit\BestChange;

use PHPUnit\Framework\TestCase;

final class AdminBulkStatusConfirmContractTest extends TestCase
{
    public function test_bulk_status_fail_closed_and_audited(): void
    {
        $ctrl = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Http/Controllers/Administrator/Crypto/BestChangeController.php'
        );
        $guard = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/Rates/BestChangeBulkStatusGuard.php'
        );
        $audit = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/Rates/AdminBulkActionAuditLogger.php'
        );

        $this->assertStringContainsString('BestChangeBulkStatusGuard::evaluate', $ctrl);
        $this->assertStringContainsString('AdminBulkActionAuditLogger::record', $ctrl);
        $this->assertStringContainsString("'ids' => ['present', 'array']", $ctrl);
        $this->assertStringContainsString('Never interpret missing/empty IDs as "all"', $ctrl);

        $this->assertStringContainsString('CODE_EMPTY_IDS', $guard);
        $this->assertStringContainsString('CODE_CONFIRM_REQUIRED', $guard);
        $this->assertStringContainsString('CODE_COUNT_MISMATCH', $guard);
        $this->assertStringContainsString('CODE_TYPED_CONFIRM_REQUIRED', $guard);
        $this->assertStringContainsString('CODE_CIRCUIT_BREAKER', $guard);
        $this->assertStringContainsString('ОТКЛЮЧИТЬ', $guard);
        $this->assertStringContainsString('CIRCUIT_BREAKER_RATIO = 0.10', $guard);
        $this->assertStringContainsString("bestchange_directions_ids_only", $guard);

        $this->assertStringContainsString('admin-bulk-audit', $audit);
        $this->assertStringContainsString('ids_hash', $audit);
        $this->assertStringContainsString('release_sha', $audit);
    }

    public function test_drift_and_snapshot_commands_registered_in_schedule_source(): void
    {
        $schedule = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Console/Schedule/ExchangeSchedule.php'
        );
        $this->assertStringContainsString('rates:ownership-drift-health', $schedule);

        $this->assertFileExists(
            dirname(__DIR__, 3) . '/app/Console/Commands/RatesOwnershipDriftHealthCommand.php'
        );
        $this->assertFileExists(
            dirname(__DIR__, 3) . '/app/Console/Commands/RatesBestChangeStatusSnapshotCommand.php'
        );
    }

    public function test_settings_index_exposes_global_default_warning(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Http/Controllers/Administrator/Crypto/BestchangeSettingsController.php'
        );

        $this->assertStringContainsString('operator_warning', $src);
        $this->assertStringContainsString('global_default_only', $src);
        $this->assertStringContainsString('bestchange_directions.position_num', $src);
    }
}
