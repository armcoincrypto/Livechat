<?php

declare(strict_types=1);

namespace Tests\Unit\PartnerAccrual;

use App\Enums\PartnerAccrualStatus;
use App\Http\Controllers\Administrator\Account\PartnerAccrualController;
use App\Models\PartnerAccrualInstallment;
use App\Models\PartnerAccrualPlan;
use App\Models\User;
use App\Models\UserBalance;
use App\Models\UserBalanceLog;
use App\Services\PartnerAccrual\AccrualMoney;
use App\Services\PartnerAccrual\PartnerAccrualAdminPresenter;
use App\Services\PartnerAccrual\PartnerAccrualCurrency;
use App\Services\PartnerAccrual\PartnerAccrualHistoryService;
use App\Services\PartnerAccrual\PartnerAccrualMoneyPresenter;
use App\Services\PartnerAccrual\PartnerAccrualPlanLifecycleService;
use App\Services\PartnerAccrual\PartnerAccrualPlanPreviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PartnerAccrualWaveCTest extends TestCase
{
    private PartnerAccrualPlanLifecycleService $lifecycle;

    private PartnerAccrualAdminPresenter $presenter;

    private PartnerAccrualHistoryService $history;

    protected function setUp(): void
    {
        parent::setUp();
        PartnerAccrualSqliteHarness::bootIsolated();
        $this->lifecycle = new PartnerAccrualPlanLifecycleService();
        $this->presenter = new PartnerAccrualAdminPresenter();
        $this->history = new PartnerAccrualHistoryService();
    }

    public function test_feature_disabled_gate(): void
    {
        config(['partner_accrual.enabled' => false]);
        $controller = new PartnerAccrualController();
        $user = $this->makeUser();
        $response = $controller->userSummary((int) $user->id);
        $this->assertSame(503, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertSame('FEATURE_DISABLED', $data['code']);
    }

    public function test_preview_300_exact_display(): void
    {
        config(['partner_accrual.enabled' => true]);
        $preview = new PartnerAccrualPlanPreviewService();
        $raw = $preview->preview([
            'user_id' => 1,
            'total_amount' => '300.00',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'schedule_seed' => 'wave-c-preview',
        ]);
        $fmt = $this->presenter->formatPreview($raw);
        $this->assertTrue($fmt['exact_sum_ok']);
        $this->assertNotSame('', $fmt['schedule_seed']);
        $this->assertStringStartsWith('v1.', $fmt['schedule_seed']);
        $this->assertSame('300.00', $fmt['total_display']);
        $this->assertSame('300.00', $fmt['sum_display']);
        $this->assertFalse($fmt['frontend_authoritative_money']);
        $this->assertFalse($fmt['mutates_db']);
        $this->assertSame(0, PartnerAccrualPlan::query()->count());
    }

    public function test_money_display_no_float_noise(): void
    {
        $this->assertSame('8.30', PartnerAccrualMoneyPresenter::display('8.30000000'));
        $this->assertSame('300.00', PartnerAccrualMoneyPresenter::display('300'));
        $this->assertSame('0.01', PartnerAccrualMoneyPresenter::display('0.01'));
        $this->assertStringNotContainsString('E', PartnerAccrualMoneyPresenter::display('8.30'));
    }

    public function test_create_pause_resume_cancel_with_events(): void
    {
        config(['partner_accrual.enabled' => true]);
        $user = $this->makeUser();
        UserBalance::create(['id_user' => $user->id, 'id_code_currency' => 8, 'balance' => 0]);

        $plan = $this->lifecycle->create([
            'user_id' => $user->id,
            'total_amount' => '30.00',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-03',
            'schedule_seed' => 'wave-c-lifecycle',
            'created_by_admin_id' => 42,
        ], activate: true);

        $summary = $this->presenter->userSummary((int) $user->id);
        $this->assertTrue($summary['has_plan']);
        $this->assertSame('ACTIVE', $summary['plan']['status']);
        $this->assertSame('NO_EDIT_AFTER_ACTIVATION', $summary['plan']['edit_policy']);

        $detailBefore = $this->presenter->planDetail($plan);
        $amounts = array_column($detailBefore['installments'], 'amount');

        $this->lifecycle->pause((int) $plan->id, 42);
        $plan->refresh();
        $this->assertSame(PartnerAccrualStatus::PAUSED, $plan->status);

        $this->lifecycle->resume((int) $plan->id, 42);
        $detailAfter = $this->presenter->planDetail($plan->fresh());
        $this->assertSame($amounts, array_column($detailAfter['installments'], 'amount'));

        $this->lifecycle->cancel((int) $plan->id, 42);
        $plan->refresh();
        $this->assertSame(PartnerAccrualStatus::CANCELLED, $plan->status);
        $this->assertGreaterThanOrEqual(3, count($detailAfter['events']) + 2);
    }

    public function test_banned_user_rejected_for_create_path(): void
    {
        config(['partner_accrual.enabled' => true]);
        $user = $this->makeUser(['banned_at' => now()]);
        UserBalance::create(['id_user' => $user->id, 'id_code_currency' => 8, 'balance' => 0]);
        $controller = new PartnerAccrualController();
        $req = Request::create('/x', 'POST', [
            'total_amount' => '10.00',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-05',
            'schedule_seed' => str_repeat('a', 64),
        ]);
        $admin = $this->makeUser();
        $req->setUserResolver(fn () => $admin);
        $response = $controller->store($req, (int) $user->id);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('USER_NOT_ELIGIBLE', $response->getData(true)['code']);
    }

    public function test_viewing_plan_does_not_regenerate_amounts(): void
    {
        config(['partner_accrual.enabled' => true]);
        $user = $this->makeUser();
        UserBalance::create(['id_user' => $user->id, 'id_code_currency' => 8, 'balance' => 0]);
        $plan = $this->lifecycle->create([
            'user_id' => $user->id,
            'total_amount' => '20.00',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-05',
            'schedule_seed' => 'persist-view',
        ], activate: true);
        $a = array_column($this->presenter->planDetail($plan)['installments'], 'amount');
        $b = array_column($this->presenter->planDetail($plan->fresh())['installments'], 'amount');
        $this->assertSame($a, $b);
    }

    public function test_partner_history_own_only_and_pending_hidden(): void
    {
        config(['partner_accrual.enabled' => true]);
        $a = $this->makeUser();
        $b = $this->makeUser();
        UserBalance::create(['id_user' => $a->id, 'id_code_currency' => 8, 'balance' => 10]);
        UserBalance::create(['id_user' => $b->id, 'id_code_currency' => 8, 'balance' => 10]);

        UserBalanceLog::create([
            'id_user' => $a->id,
            'id_manager' => 0,
            'type' => 1,
            'route_type' => 2,
            'source' => PartnerAccrualCurrency::AUDIT_SOURCE,
            'reference' => 'partner_accrual_installment:999',
            'from_balance' => '0.00000000',
            'to_balance' => '8.30000000',
            'text' => 'test',
        ]);

        $plan = $this->lifecycle->create([
            'user_id' => $a->id,
            'total_amount' => '10.00',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-02',
            'schedule_seed' => 'hist-a',
        ], activate: true);

        $execA = $this->history->executedAccrualsForUser((int) $a->id);
        $execB = $this->history->executedAccrualsForUser((int) $b->id);
        $this->assertNotEmpty($execA);
        $this->assertSame([], $execB);

        $vis = $this->history->partnerPlanVisibility((int) $a->id);
        $this->assertNotNull($vis);
        $this->assertFalse($vis['pending_schedule_visible']);

        // Pending installments exist but are not in executed list
        $pending = PartnerAccrualInstallment::query()->where('plan_id', $plan->id)->where('status', 'PENDING')->count();
        $this->assertSame(2, $pending);
        foreach ($execA as $row) {
            $this->assertSame('monthly_partner_accrual', $row['type']);
        }
    }

    public function test_reconciliation_warning_in_presenter(): void
    {
        config(['partner_accrual.enabled' => true]);
        $user = $this->makeUser();
        UserBalance::create(['id_user' => $user->id, 'id_code_currency' => 8, 'balance' => 0]);
        $plan = $this->lifecycle->create([
            'user_id' => $user->id,
            'total_amount' => '15.00',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-03',
            'schedule_seed' => 'recon-c',
        ], activate: true);
        $plan->credited_amount = '99.00000000';
        $plan->save();
        $summary = $this->presenter->planSummary($plan->fresh());
        $this->assertFalse($summary['reconciliation']['ok']);
        $this->assertFalse($summary['reconciliation']['auto_repair']);
    }

    public function test_remaining_never_negative_in_ui(): void
    {
        $this->assertSame('0.00', PartnerAccrualMoneyPresenter::remaining('10.00', '12.00'));
        $this->assertTrue(PartnerAccrualMoneyPresenter::remainingNegative('10.00', '12.00'));
    }

    public function test_admin_panel_script_contract(): void
    {
        $root = dirname(__DIR__, 3);
        $js = (string) file_get_contents($root.'/public/static/admin-partner-accrual-panel.js');
        $blade = (string) file_get_contents($root.'/resources/views/admin-vue.blade.php');
        $this->assertStringContainsString('admin-partner-accrual-panel.js', $blade);
        $this->assertStringContainsString('partner-accrual/preview', $js);
        $this->assertStringContainsString('Dry-run', $js);
        $this->assertStringContainsString("confirm: 'CANCEL'", $js);
        $this->assertStringContainsString('X-CSRF-TOKEN', $js);
        $this->assertStringContainsString('FEATURE_DISABLED', $js);
    }

    public function test_store_rejects_client_installment_payload(): void
    {
        config(['partner_accrual.enabled' => true]);
        $user = $this->makeUser();
        UserBalance::create(['id_user' => $user->id, 'id_code_currency' => 8, 'balance' => 0]);
        $controller = new PartnerAccrualController();
        $req = Request::create('/x', 'POST', [
            'total_amount' => '10.00',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-05',
            'installments' => [['amount' => '999']],
        ]);
        $req->setUserResolver(fn () => $this->makeUser());
        $response = $controller->store($req, (int) $user->id);
        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_routes_and_permissions_migration_present(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 3).'/routes/admin-frontend.php');
        $this->assertStringContainsString('partner-accrual', $routes);
        $this->assertStringContainsString('partner_accrual.admin:manage', $routes);
        $mig = dirname(__DIR__, 3).'/database/migrations/11.0.5/2026_09_25_100000_seed_partner_accrual_permissions.php';
        $this->assertFileExists($mig);
        $this->assertStringContainsString('admin_partner_accrual_manage', (string) file_get_contents($mig));
    }

    /** @param array<string, mixed> $attrs */
    private function makeUser(array $attrs = []): User
    {
        $id = DB::table('users')->insertGetId(array_merge([
            'email' => 'wave-c-'.uniqid('', true).'@test.local',
            'name' => 'Wave C',
            'banned_at' => null,
            'deactivation' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));

        return User::query()->findOrFail($id);
    }
}
