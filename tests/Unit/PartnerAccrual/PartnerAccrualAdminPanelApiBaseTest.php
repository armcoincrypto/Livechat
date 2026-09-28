<?php

declare(strict_types=1);

namespace Tests\Unit\PartnerAccrual;

use PHPUnit\Framework\TestCase;

/**
 * Guards the Wave-C admin static panel against SPA-shell URL regression.
 * Live bug: fetch(adminPrefix()+path) hit /iexadmin/account/... (HTML 200).
 * Correct: /iexadmin/frontend-api/account/...
 */
final class PartnerAccrualAdminPanelApiBaseTest extends TestCase
{
    private function panelSource(): string
    {
        $path = dirname(__DIR__, 3).'/public/static/admin-partner-accrual-panel.js';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function bladeSource(): string
    {
        $path = dirname(__DIR__, 3).'/resources/views/admin-vue.blade.php';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_panel_fetch_uses_frontend_api_prefix(): void
    {
        $src = $this->panelSource();
        $this->assertStringContainsString(
            "adminPrefix() + '/frontend-api' + path",
            $src,
            'Partner Accrual admin panel must call /frontend-api routes'
        );
        $this->assertStringNotContainsString(
            'adminPrefix() + path',
            $src,
            'Unprefixed adminPrefix()+path regresses to SPA HTML shell'
        );
    }

    public function test_panel_paths_remain_account_users_relative(): void
    {
        $src = $this->panelSource();
        $this->assertStringContainsString("/account/users/'", $src);
        $this->assertStringContainsString('partner-accrual', $src);
        $this->assertStringNotContainsString(
            "fetch('/iexadmin/account/users",
            $src
        );
        $this->assertStringNotContainsString(
            'fetch("/iexadmin/account/users',
            $src
        );
    }

    public function test_blade_includes_cache_busted_panel_script(): void
    {
        $blade = $this->bladeSource();
        $this->assertMatchesRegularExpression(
            '/admin-partner-accrual-panel\.js\?v=20260928-[a-z0-9-]+/',
            $blade
        );
    }

    public function test_panel_detects_vue_edit_user_query_param(): void
    {
        $src = $this->panelSource();
        $this->assertStringContainsString("edit_user", $src);
        $this->assertStringContainsString('URLSearchParams', $src);
        $this->assertStringContainsString('.p-dialog .p-dialog-content', $src);
        $this->assertStringContainsString('openedFromEditUserQuery', $src);
        $this->assertStringContainsString('dialogContentEl', $src);
        $this->assertStringContainsString('loadInFlight', $src);
        $this->assertStringContainsString('lastSummary', $src);
    }
}
