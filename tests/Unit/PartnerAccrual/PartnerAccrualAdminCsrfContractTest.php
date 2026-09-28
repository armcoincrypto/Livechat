<?php

declare(strict_types=1);

namespace Tests\Unit\PartnerAccrual;

use PHPUnit\Framework\TestCase;

/**
 * CSRF + authz contract for Partner Accrual admin mutations.
 *
 * Browser evidence (2026-09-28): stale meta X-CSRF-TOKEN → 419; fresh XSRF-TOKEN
 * cookie as X-XSRF-TOKEN → 200. Panel must follow the Vue Axios withXSRFToken pattern.
 * CSRF middleware itself remains enabled (no excepts / no disable).
 */
final class PartnerAccrualAdminCsrfContractTest extends TestCase
{
    private function panelSource(): string
    {
        $path = dirname(__DIR__, 3).'/public/static/admin-partner-accrual-panel.js';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function middlewareSource(string $relative): string
    {
        $path = dirname(__DIR__, 3).'/'.$relative;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_valid_csrf_request_pattern_present(): void
    {
        $src = $this->panelSource();
        $this->assertStringContainsString("headers['X-XSRF-TOKEN']", $src);
        $this->assertStringContainsString('decodeURIComponent', $src);
        $this->assertStringContainsString("credentials: 'same-origin'", $src);
        $this->assertStringContainsString('partner-accrual/preview', $src);
        $this->assertStringContainsString("'/account/users/' + userId + '/partner-accrual'", $src);
    }

    public function test_invalid_csrf_not_weakened_in_source(): void
    {
        $src = $this->panelSource();
        $this->assertStringNotContainsString('X-Skip-Csrf', $src);
        $this->assertStringNotContainsString('csrf_except', $src);
        $this->assertStringNotContainsString('withoutMiddleware', $src);
        $this->assertStringContainsString('applyCsrfHeaders', $src);

        $csrfMw = $this->middlewareSource('app/Http/Middleware/ConditionalVerifyCsrfToken.php');
        $this->assertStringNotContainsString('partner-accrual', $csrfMw);
        $this->assertStringNotContainsString('partner_accrual', $csrfMw);
    }

    public function test_dynamic_user_identity_does_not_alter_csrf_helper(): void
    {
        $src = $this->panelSource();
        $this->assertMatchesRegularExpression(
            '/function applyCsrfHeaders\(headers\)[\s\S]*?return headers;/',
            $src
        );
        if (! preg_match('/function applyCsrfHeaders\(headers\)\s*\{[\s\S]*?\n  \}/', $src, $m)) {
            $this->fail('applyCsrfHeaders body not extracted');
        }
        $csrfFn = $m[0];
        $this->assertStringNotContainsString('userId', $csrfFn);
        $this->assertStringNotContainsString('edit_user', $csrfFn);
    }

    public function test_unauthorized_rejected_contract_in_middleware(): void
    {
        $src = $this->middlewareSource('app/Http/Middleware/EnsurePartnerAccrualAdmin.php');
        $this->assertStringContainsString("'UNAUTHORIZED'", $src);
        $this->assertStringContainsString('401', $src);
        $this->assertStringContainsString("\$request->user()", $src);
        $this->assertStringContainsString("'FORBIDDEN'", $src);
        $this->assertStringContainsString('403', $src);
        $this->assertStringContainsString('admin_partner_accrual_manage', $src);
        $this->assertStringContainsString('admin_users', $src);
    }
}
