<?php

declare(strict_types=1);

namespace Tests\Unit\Kyc;

use App\Console\Commands\DiditReconcileCommand;
use PHPUnit\Framework\TestCase;

final class DiditReconcileClassifyTest extends TestCase
{
    public function test_apply_option_is_refused_in_signature(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/app/Console/Commands/DiditReconcileCommand.php');
        $this->assertStringContainsString('{--dry-run', $src);
        $this->assertStringContainsString('APPLY_REFUSED', $src);
        $this->assertStringContainsString('Never mutates KYC state', $src);
    }

    public function test_stale_unknown_session_label_helper_exists(): void
    {
        $this->assertTrue(method_exists(DiditReconcileCommand::class, 'classifyUnresolved'));
    }
}
