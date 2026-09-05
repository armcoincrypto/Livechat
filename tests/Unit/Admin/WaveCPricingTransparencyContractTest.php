<?php

declare(strict_types=1);

namespace Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;

/**
 * Wave C contracts: read-only health API, transparency surfaces, profit durability, bulk confirm.
 */
final class WaveCPricingTransparencyContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_pricing_health_endpoint_is_read_only_admin_authenticated(): void
    {
        $routes = (string) file_get_contents($this->root() . '/routes/admin-frontend.php');
        $ctrl = (string) file_get_contents($this->root() . '/app/Http/Controllers/Administrator/Basic/AdminPricingHealthController.php');

        $this->assertStringContainsString("permission:direction_exchange", $routes);
        $this->assertStringContainsString("pricing/health", $routes);
        $this->assertStringContainsString("pricing/inventory", $routes);
        $this->assertStringContainsString("pricing/explain", $routes);
        $this->assertStringContainsString('AdminPricingHealthController', $routes);

        $this->assertStringContainsString('healthSnapshot', $ctrl);
        $this->assertStringNotContainsString('->update(', $ctrl);
        $this->assertStringNotContainsString('DB::table', $ctrl); // mutations not inline; service may read
        $this->assertStringNotContainsString('password', $ctrl);
        $this->assertStringNotContainsString('api_key', $ctrl);
        $this->assertStringNotContainsString('secret', strtolower($ctrl));
    }

    public function test_health_snapshot_service_exposes_owner_signals_without_secrets(): void
    {
        $src = (string) file_get_contents($this->root() . '/app/Services/Rates/AdminPricingTransparency.php');
        foreach ([
            'ownership_counts',
            'zero_profit',
            'stale_critical_count',
            'last_bestchange_update',
            'global_bestchange_position',
            'HEALTHY',
            'AGING',
            'STALE',
            'SOURCE_ERROR',
            'profit_survives_hint',
        ] as $needle) {
            $this->assertStringContainsString($needle, $src);
        }
        $this->assertStringNotContainsString('BESTCHANGE_API', $src);
        $this->assertStringNotContainsString('password', strtolower($src));
    }

    public function test_effective_position_layers_documented_in_service(): void
    {
        $src = (string) file_get_contents($this->root() . '/app/Services/Rates/AdminPricingTransparency.php');
        $this->assertStringContainsString("'global'", $src);
        $this->assertStringContainsString("'pair_override'", $src);
        $this->assertStringContainsString("'effective'", $src);
        $this->assertStringContainsString('has_pair_override', $src);
    }

    public function test_bc_health_prefers_active_mapping_row(): void
    {
        $src = (string) file_get_contents($this->root() . '/app/Services/Rates/BestChangeMarketBaseHealth.php');
        $this->assertStringContainsString("orderByDesc('status')", $src);
        $this->assertStringContainsString('Prefer the active mapping', $src);
    }

    public function test_profit_durability_contracts_in_compilers(): void
    {
        $derived = (string) file_get_contents($this->root() . '/app/Services/Rates/DerivedMarketBaselineAuthority.php');
        $this->assertStringContainsString('Do NOT zero profit', $derived);

        $zelle = (string) file_get_contents($this->root() . '/app/Services/Rates/ZelleUsdUsdtBenchmarkAuthority.php');
        $this->assertStringContainsString("'profit' => 0", $zelle);
        $this->assertStringNotContainsString("'floating_fee' =>", $zelle); // must not overwrite owner floating_fee on BASE refresh

        $gate = (string) file_get_contents($this->root() . '/app/Services/Rates/CommercialAdjustmentWriteGate.php');
        $this->assertStringContainsString('admin:DirectionExchangeProfitController', $gate);
        $this->assertStringContainsString('UNAUTHORIZED_PROFIT_WRITE', $gate);
    }

    public function test_zero_profit_and_owner_filters_exist(): void
    {
        $filter = (string) file_get_contents($this->root() . '/app/Models/Filters/DirectionExchangeFilter.php');
        $this->assertStringContainsString('function zeroProfit', $filter);
        $this->assertStringContainsString('function pricingOwner', $filter);
        $this->assertStringContainsString('function positionOverride', $filter);

        $resource = (string) file_get_contents($this->root() . '/app/Http/Resources/Admin/Basic/DirectionExchangeResources.php');
        $this->assertStringContainsString('zero_profit_note', $resource);
        $this->assertStringContainsString('effective_bestchange_position', $resource);
        $this->assertStringContainsString('display_profit', $resource);
    }

    public function test_admin_overlay_scripts_registered_russian_labels(): void
    {
        $blade = (string) file_get_contents($this->root() . '/resources/views/admin-vue.blade.php');
        $this->assertStringContainsString('admin-pricing-health-strip.js', $blade);
        $this->assertStringContainsString('admin-pricing-ownership-panel.js', $blade);

        $panel = (string) file_get_contents($this->root() . '/public/static/admin-pricing-ownership-panel.js');
        $this->assertStringContainsString('Почему такой курс?', $panel);
        $this->assertStringContainsString('Источник курса', $panel);
        $this->assertStringContainsString('Эффективная позиция', $panel);
        $this->assertStringContainsString('Прибыль', $panel);

        $strip = (string) file_get_contents($this->root() . '/public/static/admin-pricing-health-strip.js');
        $this->assertStringContainsString('Курсы в норме', $strip);
        $this->assertStringContainsString('Прибыль = 0', $strip);
        $this->assertStringContainsString('/administrator/pricing/health', $strip);
    }

    public function test_bulk_activate_confirmation_still_required(): void
    {
        $ctrl = (string) file_get_contents($this->root() . '/app/Http/Controllers/Administrator/Basic/DirectionExchangeController.php');
        $this->assertStringContainsString('confirm_activate_without_course', $ctrl);
        $this->assertStringContainsString('bulkChangeStatus', $ctrl);
    }

    public function test_profit_controller_still_has_update_and_uses_transparency(): void
    {
        $ctrl = (string) file_get_contents(
            $this->root() . '/app/Http/Controllers/Administrator/Basic/DirectionExchange/Fees/DirectionExchangeProfitController.php'
        );
        $this->assertStringContainsString('function update', $ctrl);
        $this->assertStringContainsString('AdminPricingTransparency::explain', $ctrl);
        $this->assertStringContainsString('CommercialAdjustmentWriteGate', $ctrl);
    }
}
