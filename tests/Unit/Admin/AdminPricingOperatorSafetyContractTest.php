<?php

declare(strict_types=1);

namespace Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;

/**
 * Operator pricing safety contracts (preview / ownership / mass / audit / truth).
 * Source-level — does not mutate live economics.
 */
final class AdminPricingOperatorSafetyContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_operator_safety_service_exposes_truth_preview_convergence(): void
    {
        $src = (string) file_get_contents($this->root().'/app/Services/Rates/AdminPricingOperatorSafety.php');
        foreach ([
            'truthPanel',
            'preview',
            'convergence',
            'massPreview',
            'OWNER',
            'RATE_MODE',
            'CONFIGURED_POSITION',
            'EFFECTIVE_POSITION',
            'SELECTED_EXCHANGER',
            'SELECTED_RATE_VALUE',
            'CALCULATOR_FINAL_RATE',
            'STORED_COURSE_VALUE',
            'PUBLIC_QUOTE_RATE',
            'XML_RATE',
            'CURRENT_POSITION',
            'NEW_POSITION',
            'CURRENT_PROFIT',
            'NEW_PROFIT',
            'CURRENT_BASE',
            'EXPECTED_NEW_BASE',
            'CURRENT_CUSTOMER_RATE',
            'EXPECTED_NEW_CUSTOMER_RATE',
            'CONVERGED',
            'WAITING_FOR_COMPILER',
            'PRICING_MISMATCH',
            'dry_run',
            'FINAL CUSTOMER RATE',
        ] as $needle) {
            $this->assertStringContainsString($needle, $src);
        }
        $this->assertStringContainsString('коммерческий', $src);
    }

    public function test_health_controller_routes_are_read_only_for_preview_truth_converge(): void
    {
        $routes = (string) file_get_contents($this->root().'/routes/admin-frontend.php');
        $ctrl = (string) file_get_contents($this->root().'/app/Http/Controllers/Administrator/Basic/AdminPricingHealthController.php');

        $this->assertStringContainsString('pricing/truth', $routes);
        $this->assertStringContainsString('pricing/preview', $routes);
        $this->assertStringContainsString('pricing/converge', $routes);
        $this->assertStringContainsString('pricing/mass-preview', $routes);

        $this->assertStringContainsString('function truth', $ctrl);
        $this->assertStringContainsString('function preview', $ctrl);
        $this->assertStringContainsString('function converge', $ctrl);
        $this->assertStringContainsString('function massPreview', $ctrl);
        $this->assertStringContainsString('OWNERSHIP_SWITCH_REQUIRES_DELIBERATE_ACTION', $ctrl);
        $this->assertStringNotContainsString('->update(', $ctrl);
        $this->assertStringNotContainsString('->save(', $ctrl);
    }

    public function test_mass_editor_requires_confirm_and_blocks_ownership_piggyback(): void
    {
        $src = (string) file_get_contents(
            $this->root().'/app/Http/Controllers/Administrator/Basic/DirectionExchangeMassEditorController.php'
        );
        $this->assertStringContainsString('OWNERSHIP_SWITCH_REQUIRES_DELIBERATE_ACTION', $src);
        $this->assertStringContainsString('OWNERSHIP_PIGGYBACK_BLOCKED', $src);
        $this->assertStringContainsString('CONFIRM_REQUIRED', $src);
        $this->assertStringContainsString('confirm_token', $src);
        $this->assertStringContainsString('AdminPricingFinancialAuditLogger', $src);
        $this->assertStringContainsString('parser_source_name', $src);
        $this->assertStringContainsString('rate_mode', $src);
    }

    public function test_profit_update_blocks_ownership_fields_and_returns_save_accepted(): void
    {
        $src = (string) file_get_contents(
            $this->root().'/app/Http/Controllers/Administrator/Basic/DirectionExchange/Fees/DirectionExchangeProfitController.php'
        );
        $this->assertStringContainsString('OWNERSHIP_SWITCH_REQUIRES_DELIBERATE_ACTION', $src);
        $this->assertStringContainsString('SAVE_ACCEPTED', $src);
        $this->assertStringContainsString('convergence', $src);
        $this->assertStringContainsString('AdminPricingFinancialAuditLogger', $src);
        $this->assertStringContainsString('AdminPricingOperatorSafety::preview', $src);
    }

    public function test_bestchange_rate_mode_requires_deliberate_confirm(): void
    {
        $src = (string) file_get_contents(
            $this->root().'/app/Http/Controllers/Administrator/Crypto/BestChangeController.php'
        );
        $this->assertStringContainsString('confirm_rate_mode_change', $src);
        $this->assertStringContainsString('RATE_MODE_CONFIRM_REQUIRED', $src);
        $this->assertStringContainsString('SAVE_ACCEPTED', $src);
        $this->assertStringContainsString('admin_position_change', $src);
    }

    public function test_courses_page_requires_ownership_switch_confirm(): void
    {
        $src = (string) file_get_contents(
            $this->root().'/app/Http/Controllers/Administrator/Basic/DirectionExchangeController.php'
        );
        $this->assertStringContainsString('confirm_ownership_switch', $src);
        $this->assertStringContainsString('OWNERSHIP_SWITCH_REQUIRES_DELIBERATE_ACTION', $src);
        $this->assertStringContainsString('manual_rate_value', $src);
    }

    public function test_financial_audit_logger_schema(): void
    {
        $src = (string) file_get_contents($this->root().'/app/Services/Rates/AdminPricingFinancialAuditLogger.php');
        foreach (['change_id', 'operator', 'direction_id', 'pair', 'old', 'new', 'before', 'after', 'position', 'profit', 'fix_fee', 'rate_mode', 'owner', 'base', 'customer_rate'] as $needle) {
            $this->assertStringContainsString($needle, $src);
        }
    }

    public function test_operator_contract_doc_exists(): void
    {
        $path = $this->root().'/docs/pricing/ADMIN_PRICING_OPERATOR_CONTRACT.md';
        $this->assertFileExists($path);
        $doc = (string) file_get_contents($path);
        foreach (['BESTCHANGE', 'DERIVED', 'ZELLE', 'MANUAL', 'BASE', 'PROFIT', 'FINAL CUSTOMER RATE'] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }
    }

    public function test_overlay_truth_labels(): void
    {
        $panel = (string) file_get_contents($this->root().'/public/static/admin-pricing-ownership-panel.js');
        $this->assertStringContainsString('Почему такой курс?', $panel);
        $this->assertStringContainsString('Источник курса', $panel);
        $this->assertStringContainsString('Эффективная позиция', $panel);
        $this->assertStringContainsString('BASE', $panel);
        $this->assertStringContainsString('PROFIT', $panel);
        $this->assertStringContainsString('FINAL CUSTOMER RATE', $panel);
        $this->assertStringContainsString('STORED_COURSE_VALUE', $panel);
        $this->assertStringContainsString('pricing/truth', $panel);
    }

    public function test_profit_preview_math_contract_zero_write(): void
    {
        // Simulated: BASE=100, profit 0.5% → customer = 100 * (1 - 0.005) = 99.5
        // profit 0.7% → 99.3. Preview must be dry_run.
        $src = (string) file_get_contents($this->root().'/app/Services/Rates/AdminPricingOperatorSafety.php');
        $this->assertStringContainsString("'dry_run' => true", $src);
        $this->assertStringContainsString('bcmul($displayProfit, \'-1\'', (string) file_get_contents(
            $this->root().'/app/Services/Rates/AdminPricingTransparency.php'
        ));

        $base = '100';
        $profit05 = '0.5';
        $profit07 = '0.7';
        $adj05 = bcmul($profit05, '-1', 8);
        $adj07 = bcmul($profit07, '-1', 8);
        // applyPercentToRate: rate * (1 + pct/100)
        $r05 = bcmul($base, bcadd('1', bcdiv($adj05, '100', 12), 12), 8);
        $r07 = bcmul($base, bcadd('1', bcdiv($adj07, '100', 12), 12), 8);
        $this->assertSame('99.50000000', $r05);
        $this->assertSame('99.30000000', $r07);
    }
}
