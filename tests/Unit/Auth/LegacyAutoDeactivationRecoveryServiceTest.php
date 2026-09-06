<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Console\Commands\RecoverLegacyAutoDeactivationCommand;
use App\Services\Auth\LegacyAutoDeactivationRecoveryService;
use PHPUnit\Framework\TestCase;

final class LegacyAutoDeactivationRecoveryServiceTest extends TestCase
{
    public function test_command_signature_is_scoped(): void
    {
        $cmd = new RecoverLegacyAutoDeactivationCommand();
        $sig = $cmd->getName();
        self::assertSame('users:recover-legacy-auto-deactivation', $sig);
        $definition = $cmd->getDefinition();
        self::assertTrue($definition->hasOption('dry-run'));
        self::assertTrue($definition->hasOption('apply'));
        self::assertTrue($definition->hasOption('cohort'));
        self::assertTrue($definition->hasOption('limit'));
    }

    public function test_service_version_and_artifact_dir(): void
    {
        self::assertSame('legacy-recovery-v1', LegacyAutoDeactivationRecoveryService::VERSION);
        self::assertStringContainsString('legacy-account-recovery', LegacyAutoDeactivationRecoveryService::ARTIFACT_DIR);
    }

    public function test_command_source_forbids_email_and_password_mutation(): void
    {
        $src = file_get_contents(base_path('app/Services/Auth/LegacyAutoDeactivationRecoveryService.php'));
        self::assertIsString($src);
        self::assertStringContainsString("forceFill(['deactivation' => 0])", $src);
        self::assertStringNotContainsString('email_verified_at', explode('reactivateIds', $src)[1] ?? '');
        // reactivateIds must not call markEmailAsVerified / set password
        $reactivate = explode('function reactivateIds', $src)[1] ?? '';
        self::assertStringNotContainsString('markEmailAsVerified', $reactivate);
        self::assertStringNotContainsString('password', strtolower($reactivate));
    }
}
