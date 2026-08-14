<?php
declare(strict_types=1);
namespace Tests\Unit\Rates;
use App\Services\Rates\UsdtRubCommercialTargetSyncService;
use PHPUnit\Framework\TestCase;
final class ManualProfitOwnershipContractTest extends TestCase
{
    public function test_auto_target_disabled_by_default(): void
    {
        putenv(UsdtRubCommercialTargetSyncService::AUTO_TARGET_ENV);
        unset($_ENV[UsdtRubCommercialTargetSyncService::AUTO_TARGET_ENV], $_SERVER[UsdtRubCommercialTargetSyncService::AUTO_TARGET_ENV]);
        $svc = UsdtRubCommercialTargetSyncService::make();
        $this->assertFalse($svc->autoTargetEnabled());
        $r = $svc->sync(dryRun: true);
        $this->assertTrue($r['ok']);
        $this->assertSame('manual_profit_ownership', $r['reason']);
    }
    public function test_policy_manual_mode(): void
    {
        $raw = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/rates/rub-family-premium-policy.json'), true);
        $mode = $raw['intentional_commercial']['commercial_mode'] ?? $raw['intentional_commercial']['mode'] ?? null;
        $this->assertSame('MANUAL_PROFIT', $mode);
        $this->assertArrayNotHasKey('target_commercial_usdt_rub', $raw['families']['SBERRUB'] ?? []);
    }
    public function test_cron_no_live_sync_write(): void
    {
        $src = (string) file_get_contents('/opt/exswaping-owned-frontend/scripts/ops/refresh_crypto_usdt_baselines.sh');
        $this->assertStringContainsString('MANUAL_PROFIT', $src);
        $this->assertStringNotContainsString('->sync(dryRun: false)', $src);
    }
}
