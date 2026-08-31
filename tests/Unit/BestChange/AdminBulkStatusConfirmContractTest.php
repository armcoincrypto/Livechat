<?php

declare(strict_types=1);

namespace Tests\Unit\BestChange;

use PHPUnit\Framework\TestCase;

final class AdminBulkStatusConfirmContractTest extends TestCase
{
    public function test_bulk_status_requires_confirm_flag(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Http/Controllers/Administrator/Crypto/BestChangeController.php'
        );

        $this->assertStringContainsString('BULK_STATUS_CONFIRM_REQUIRED', $src);
        $this->assertStringContainsString("bestchange_directions_ids_only", $src);
        $this->assertStringContainsString('!$request->boolean(\'confirm\')', $src);
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
