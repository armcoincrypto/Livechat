<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\CommercialAdjustmentWriteGate;
use App\Services\Rates\RubFamilyPremiumPolicy;
use App\Services\Rates\UsdtRubCommercialTargetSyncService;
use PHPUnit\Framework\TestCase;

final class UsdtRubCommercialTargetSyncServiceTest extends TestCase
{
    public function test_profit_for_target_math_uses_bcmath(): void
    {
        $base = '83.212429275000';
        $target = '95';
        $profit = UsdtRubCommercialTargetSyncService::profitForTarget($base, $target);
        $this->assertNotNull($profit);
        $fee = bcmul($profit, '-1', 12);
        $final = bcmul($base, bcadd('1', bcdiv($fee, '100', 18), 18), 12);
        $this->assertTrue(abs((float) $final - 95.0) < 0.05, "final={$final}");
        $this->assertNotSame(
            UsdtRubCommercialTargetSyncService::LEGACY_MAGIC_PROFIT,
            bcmul($profit, '1', 6)
        );
    }

    public function test_profit_for_target_rejects_non_positive_base(): void
    {
        $this->assertNull(UsdtRubCommercialTargetSyncService::profitForTarget('0', '95'));
        $this->assertNull(UsdtRubCommercialTargetSyncService::profitForTarget('-1', '95'));
    }

    public function test_policy_manual_profit_has_no_absolute_target(): void
    {
        $path = dirname(__DIR__, 3).'/resources/rates/rub-family-premium-policy.json';
        $this->assertFileExists($path);
        $raw = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $policy = new RubFamilyPremiumPolicy($raw);
        $this->assertTrue($policy->isApproved());
        $mode = $raw['intentional_commercial']['commercial_mode']
            ?? $raw['intentional_commercial']['mode']
            ?? null;
        $this->assertSame('MANUAL_PROFIT', $mode);
        $sync = new UsdtRubCommercialTargetSyncService($policy);
        $this->assertNull($sync->absoluteTarget());
        $this->assertNull($sync->absoluteTarget('SBERRUB'));
        $this->assertFalse($sync->autoTargetEnabled());
        $codes = $sync->eligibleDestinationCodes();
        foreach (['SBERRUB', 'TBRUB', 'TCSBRUB', 'SBPRUB', 'RFBRUB', 'ACRUB'] as $code) {
            $this->assertContains($code, $codes);
        }
        $this->assertNotContains('CARDRUB', $codes);
        $this->assertNotContains('YAMRUB', $codes);
    }

    public function test_write_gate_includes_policy_sync_writer(): void
    {
        $this->assertContains(
            UsdtRubCommercialTargetSyncService::WRITER,
            CommercialAdjustmentWriteGate::approvedWriters()
        );
        CommercialAdjustmentWriteGate::run(UsdtRubCommercialTargetSyncService::WRITER, function (): void {
            $this->assertTrue(CommercialAdjustmentWriteGate::isApproved());
            $this->assertSame(
                UsdtRubCommercialTargetSyncService::WRITER,
                CommercialAdjustmentWriteGate::currentWriter()
            );
        });
    }

    public function test_command_documents_sync_commercial_and_keeps_apply_profit_safe(): void
    {
        $path = dirname(__DIR__, 3).'/app/Console/Commands/RatesApplyUsdtRubCommercialTargetCommand.php';
        $src = (string) file_get_contents($path);
        $this->assertStringContainsString('--sync-commercial', $src);
        $this->assertStringContainsString('UsdtRubCommercialTargetSyncService', $src);
        $this->assertStringContainsString('legacy-overwrite-owner-profit', $src);

        $withoutLegacy = preg_replace(
            '/CommercialAdjustmentWriteGate::run\(.*?^\s*\}\);/ms',
            '/* gate removed */',
            $src
        );
        $this->assertIsString($withoutLegacy);
        $this->assertStringNotContainsString('$dir->profit = $item[\'reference_profit_for_target\']', (string) $withoutLegacy);
    }
}
