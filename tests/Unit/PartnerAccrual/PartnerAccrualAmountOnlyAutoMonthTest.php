<?php

declare(strict_types=1);

namespace Tests\Unit\PartnerAccrual;

use App\Services\PartnerAccrual\AccrualMoney;
use App\Services\PartnerAccrual\PartnerAccrualPeriodResolver;
use App\Services\PartnerAccrual\PartnerAccrualPlanPreviewService;
use App\Services\PartnerAccrual\PartnerAccrualScheduleSeedBinder;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\TestCase;

/**
 * Amount-only admin contract: auto next-month period + seed binding.
 * Uses isolated sqlite for overlap create paths when needed.
 */
final class PartnerAccrualAmountOnlyAutoMonthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('partner_accrual.enabled', true);
        Config::set('partner_accrual.business_timezone', 'Europe/Moscow');
        if (! Config::get('app.key')) {
            Config::set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        }
        // Freeze "today" indirectly by resolving period for known dates in unit tests;
        // controller uses live clock — preview service path tested with explicit dates via resolver.
    }

    public function test_panel_is_amount_only_no_manual_date_inputs(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 3).'/public/static/admin-partner-accrual-panel.js');
        $this->assertStringContainsString('Месячный бюджет', $js);
        $this->assertStringContainsString('следующий полный календарный месяц', $js);
        $this->assertStringContainsString('formatRuDate', $js);
        $this->assertStringNotContainsString("id=\"exs-pa-start\"", $js);
        $this->assertStringNotContainsString("id=\"exs-pa-end\"", $js);
        $this->assertStringNotContainsString('type="date"', $js);
        // Create payload must not send operator dates.
        if (! preg_match('/mutate\(\'POST\', \'\/account\/users\/\' \+ userId \+ \'\/partner-accrual\', \{[\s\S]*?\}, userId\);/', $js, $m)) {
            $this->fail('create mutate block not found');
        }
        $this->assertStringContainsString('total_amount: budget', $m[0]);
        $this->assertStringContainsString('schedule_seed: seed', $m[0]);
        $this->assertStringNotContainsString('start_date', $m[0]);
        $this->assertStringNotContainsString('end_date', $m[0]);
        // Preview API body also amount-only.
        $this->assertStringContainsString(
            "api('POST', '/account/users/' + userId + '/partner-accrual/preview', {\n      total_amount: budget,\n      currency_code: 'USD'\n    })",
            $js
        );
    }

    public function test_controller_preview_validation_no_longer_requires_dates(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3).'/app/Http/Controllers/Administrator/Account/PartnerAccrualController.php'
        );
        $this->assertStringContainsString('PartnerAccrualPeriodResolver', $src);
        $this->assertStringContainsString('nextFullCalendarMonth', $src);
        $this->assertStringContainsString('period_mode', $src);
        // Preview rules should not require start_date/end_date.
        $this->assertDoesNotMatchRegularExpression(
            "/'start_date'\\s*=>\\s*'required/",
            $src
        );
        $this->assertDoesNotMatchRegularExpression(
            "/'end_date'\\s*=>\\s*'required/",
            $src
        );
    }

    public function test_amount_only_preview_parity_with_resolver_period(): void
    {
        $resolver = new PartnerAccrualPeriodResolver();
        $period = $resolver->nextFullCalendarMonth();
        $preview = new PartnerAccrualPlanPreviewService();
        $out = $preview->preview([
            'user_id' => 42,
            'currency_code' => 'USD',
            'total_amount' => '100.00',
            'start_date' => $period['start_date'],
            'end_date' => $period['end_date'],
        ]);
        $this->assertTrue($out['mutates_db'] === false);
        $this->assertSame($period['start_date'], $out['schedule'][0]['scheduled_for']);
        $last = $out['schedule'][count($out['schedule']) - 1]['scheduled_for'];
        $this->assertSame($period['end_date'], $last);
        $this->assertSame('100.00000000', AccrualMoney::scale8(
            AccrualMoney::of($out['sum'])
        ));
        $this->assertNotSame('', $out['schedule_seed']);
    }

    public function test_signed_seed_binds_auto_period_and_rejects_forged_period(): void
    {
        $resolver = new PartnerAccrualPeriodResolver();
        $period = $resolver->nextFullCalendarMonth();
        $preview = new PartnerAccrualPlanPreviewService();
        $binder = new PartnerAccrualScheduleSeedBinder();

        $out = $preview->preview([
            'user_id' => 7,
            'total_amount' => '50.00',
            'start_date' => $period['start_date'],
            'end_date' => $period['end_date'],
            'currency_code' => 'USD',
        ]);
        $seed = (string) $out['schedule_seed'];

        // Matching create context OK.
        $nonce = $binder->assertMatches($seed, [
            'user_id' => 7,
            'total_amount' => '50.00',
            'start_date' => $period['start_date'],
            'end_date' => $period['end_date'],
            'currency_code' => 'USD',
        ]);
        $this->assertNotSame('', $nonce);

        $this->expectException(\App\Services\PartnerAccrual\Exceptions\InvalidScheduleSeed::class);
        $binder->assertMatches($seed, [
            'user_id' => 7,
            'total_amount' => '50.00',
            'start_date' => '2099-01-01',
            'end_date' => '2099-01-31',
            'currency_code' => 'USD',
        ]);
    }

    public function test_stale_amount_after_preview_rejected_by_seed(): void
    {
        $resolver = new PartnerAccrualPeriodResolver();
        $period = $resolver->nextFullCalendarMonth();
        $preview = new PartnerAccrualPlanPreviewService();
        $binder = new PartnerAccrualScheduleSeedBinder();
        $out = $preview->preview([
            'user_id' => 9,
            'total_amount' => '100.00',
            'start_date' => $period['start_date'],
            'end_date' => $period['end_date'],
        ]);
        $this->expectException(\App\Services\PartnerAccrual\Exceptions\InvalidScheduleSeed::class);
        $binder->assertMatches((string) $out['schedule_seed'], [
            'user_id' => 9,
            'total_amount' => '999.00',
            'start_date' => $period['start_date'],
            'end_date' => $period['end_date'],
        ]);
    }

    public function test_blade_cache_bust_amount_only(): void
    {
        $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin-vue.blade.php');
        $this->assertMatchesRegularExpression(
            '/admin-partner-accrual-panel\\.js\\?v=20260928-amount-only-month/',
            $blade
        );
    }

    public function test_csrf_helper_still_present(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 3).'/public/static/admin-partner-accrual-panel.js');
        $this->assertStringContainsString('applyCsrfHeaders', $js);
        $this->assertStringContainsString('X-XSRF-TOKEN', $js);
    }

    public function test_rbac_middleware_unchanged(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3).'/app/Http/Middleware/EnsurePartnerAccrualAdmin.php'
        );
        $this->assertStringContainsString('UNAUTHORIZED', $src);
        $this->assertStringContainsString('FORBIDDEN', $src);
        $this->assertStringContainsString('admin_partner_accrual_manage', $src);
    }

    public function test_period_resolver_class_exists_and_mode_constant(): void
    {
        $this->assertSame(
            'NEXT_FULL_CALENDAR_MONTH',
            PartnerAccrualPeriodResolver::MODE_NEXT_FULL_CALENDAR_MONTH
        );
    }
}
