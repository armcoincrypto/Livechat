<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\CommercialAdjustmentWriteGate;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Owner policy: RUB commercial-target automation must not mutate Прибыль.
 */
final class UsdtRubCommercialTargetOwnerProfitGuardTest extends TestCase
{
    public function test_command_source_never_assigns_profit_on_normal_apply_path(): void
    {
        $path = dirname(__DIR__, 3).'/app/Console/Commands/RatesApplyUsdtRubCommercialTargetCommand.php';
        $this->assertFileExists($path);
        $src = (string) file_get_contents($path);

        // Normal apply must document protection and must not bare-assign profit outside legacy gate.
        $this->assertStringContainsString('owner_profit_protected', $src);
        $this->assertStringContainsString('legacy-overwrite-owner-profit', $src);
        $this->assertStringContainsString('EXSWAPING_ALLOW_LEGACY_RUB_PROFIT_BOOTSTRAP', $src);

        // Extract non-legacy region: profit assignment only allowed inside CommercialAdjustmentWriteGate::run legacy block.
        $this->assertMatchesRegularExpression(
            '/CommercialAdjustmentWriteGate::run\(\'legacy:RatesApplyUsdtRubCommercialTargetCommand\'/',
            $src
        );

        // Ensure floating_fee / fix_fee are not force-zeroed outside legacy.
        $withoutLegacy = preg_replace(
            '/CommercialAdjustmentWriteGate::run\(.*?^\s*\}\);/ms',
            '/* legacy removed */',
            $src
        );
        $this->assertIsString($withoutLegacy);
        $this->assertStringNotContainsString('$dir->profit =', (string) $withoutLegacy);
        $this->assertStringNotContainsString('$dir->floating_fee = 0', (string) $withoutLegacy);
        $this->assertStringNotContainsString('$dir->fix_fee =', (string) $withoutLegacy);
    }

    public function test_write_gate_stack_approves_nested_writers(): void
    {
        $this->assertFalse(CommercialAdjustmentWriteGate::isApproved());
        CommercialAdjustmentWriteGate::run('admin:DirectionExchangeProfitController', function (): void {
            $this->assertTrue(CommercialAdjustmentWriteGate::isApproved());
            $this->assertSame(
                'admin:DirectionExchangeProfitController',
                CommercialAdjustmentWriteGate::currentWriter()
            );
        });
        $this->assertFalse(CommercialAdjustmentWriteGate::isApproved());
    }

    public function test_legacy_bootstrap_requires_env_dual_gate_method(): void
    {
        $ref = new ReflectionClass(\App\Console\Commands\RatesApplyUsdtRubCommercialTargetCommand::class);
        $m = $ref->getMethod('legacyProfitBootstrapAllowed');
        $m->setAccessible(true);
        $cmd = $ref->newInstanceWithoutConstructor();

        $prev = getenv('EXSWAPING_ALLOW_LEGACY_RUB_PROFIT_BOOTSTRAP');
        putenv('EXSWAPING_ALLOW_LEGACY_RUB_PROFIT_BOOTSTRAP');
        $_ENV['EXSWAPING_ALLOW_LEGACY_RUB_PROFIT_BOOTSTRAP'] = '';
        $_SERVER['EXSWAPING_ALLOW_LEGACY_RUB_PROFIT_BOOTSTRAP'] = '';
        $this->assertFalse($m->invoke($cmd));

        putenv('EXSWAPING_ALLOW_LEGACY_RUB_PROFIT_BOOTSTRAP=1');
        $_ENV['EXSWAPING_ALLOW_LEGACY_RUB_PROFIT_BOOTSTRAP'] = '1';
        $_SERVER['EXSWAPING_ALLOW_LEGACY_RUB_PROFIT_BOOTSTRAP'] = '1';
        $this->assertTrue($m->invoke($cmd));

        if ($prev === false) {
            putenv('EXSWAPING_ALLOW_LEGACY_RUB_PROFIT_BOOTSTRAP');
            unset($_ENV['EXSWAPING_ALLOW_LEGACY_RUB_PROFIT_BOOTSTRAP'], $_SERVER['EXSWAPING_ALLOW_LEGACY_RUB_PROFIT_BOOTSTRAP']);
        } else {
            putenv('EXSWAPING_ALLOW_LEGACY_RUB_PROFIT_BOOTSTRAP='.$prev);
            $_ENV['EXSWAPING_ALLOW_LEGACY_RUB_PROFIT_BOOTSTRAP'] = $prev;
            $_SERVER['EXSWAPING_ALLOW_LEGACY_RUB_PROFIT_BOOTSTRAP'] = $prev;
        }
    }
}
