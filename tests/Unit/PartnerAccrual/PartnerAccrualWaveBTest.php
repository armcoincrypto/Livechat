<?php

declare(strict_types=1);

namespace Tests\Unit\PartnerAccrual;

use App\Enums\PartnerAccrualInstallmentStatus;
use App\Enums\PartnerAccrualStatus;
use App\Jobs\ProcessPartnerAccrualInstallmentJob;
use App\Models\PartnerAccrualInstallment;
use App\Models\PartnerAccrualPlan;
use App\Models\PartnerAccrualPlanEvent;
use App\Models\User;
use App\Models\UserBalance;
use App\Models\UserBalanceLog;
use App\Services\PartnerAccrual\AccrualMoney;
use App\Services\PartnerAccrual\AdminPartnerBalanceAbsoluteSet;
use App\Services\PartnerAccrual\CreditResult;
use App\Services\PartnerAccrual\Exceptions\GeneratorConstraintViolation;
use App\Services\PartnerAccrual\Exceptions\OverlappingPlanExists;
use App\Services\PartnerAccrual\PartnerAccrualCreditService;
use App\Services\PartnerAccrual\PartnerAccrualCurrency;
use App\Services\PartnerAccrual\PartnerAccrualObservability;
use App\Services\PartnerAccrual\PartnerAccrualPlanGenerator;
use App\Services\PartnerAccrual\PartnerAccrualPlanLifecycleService;
use App\Services\PartnerAccrual\PartnerAccrualPlanPreviewService;
use App\Services\PartnerAccrual\PartnerAccrualReconciliationService;
use App\Services\PartnerAccrual\PartnerAccrualRunner;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PartnerAccrualWaveBTest extends TestCase
{
    private PartnerAccrualPlanGenerator $generator;

    private PartnerAccrualPlanPreviewService $preview;

    private PartnerAccrualPlanLifecycleService $lifecycle;

    private PartnerAccrualRunner $runner;

    private PartnerAccrualCreditService $credit;

    protected function setUp(): void
    {
        parent::setUp();
        PartnerAccrualSqliteHarness::bootIsolated();
        $this->generator = new PartnerAccrualPlanGenerator();
        $this->preview = new PartnerAccrualPlanPreviewService($this->generator);
        $this->lifecycle = new PartnerAccrualPlanLifecycleService($this->generator);
        $this->runner = new PartnerAccrualRunner();
        $this->credit = new PartnerAccrualCreditService();
    }

    public function test_300_usd_31_day_exact_sum_and_print_schedule(): void
    {
        $g = $this->generator->generate([
            'total_amount' => '300.00',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'schedule_seed' => 'wave-b-exemplar-300-31',
        ]);

        $this->assertSame(31, $g['installment_count']);
        $this->assertSame('300.00000000', $g['sum']);
        $this->assertSame('300.00000000', $g['total_amount']);
        $this->assertGreaterThan(0, AccrualMoney::cmp(AccrualMoney::of($g['min']), AccrualMoney::zero()));
        $this->assertTrue(AccrualMoney::cmp(AccrualMoney::of($g['max']), AccrualMoney::of($g['min'])) >= 0);

        fwrite(STDOUT, "EXEMPLAR_TOTAL=300.00\n");
        fwrite(STDOUT, "EXEMPLAR_DAYS=31\n");
        fwrite(STDOUT, 'EXEMPLAR_MIN='.$g['min']."\n");
        fwrite(STDOUT, 'EXEMPLAR_MAX='.$g['max']."\n");
        fwrite(STDOUT, 'EXEMPLAR_AVG='.$g['average']."\n");
        foreach ($g['schedule'] as $row) {
            fwrite(STDOUT, sprintf("EXEMPLAR_ROW seq=%d date=%s amount=%s\n", $row['sequence'], $row['scheduled_for'], $row['amount']));
        }
    }

    public function test_month_lengths_28_29_30_31_exact(): void
    {
        $cases = [
            ['2026-02-01', '2026-02-28', 28],
            ['2024-02-01', '2024-02-29', 29],
            ['2026-04-01', '2026-04-30', 30],
            ['2026-01-01', '2026-01-31', 31],
        ];
        foreach ($cases as [$start, $end, $days]) {
            $g = $this->generator->generate([
                'total_amount' => '100.00',
                'start_date' => $start,
                'end_date' => $end,
                'schedule_seed' => 'month-'.$days,
            ]);
            $this->assertSame($days, $g['installment_count'], "days=$days");
            $this->assertSame('100.00000000', $g['sum'], "sum days=$days");
        }
    }

    public function test_edge_budgets_and_impossible_constraints(): void
    {
        $one = $this->generator->generate([
            'total_amount' => '1.00',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'schedule_seed' => 'one-dollar',
            'min_installment' => '0.01',
            'max_installment' => '1.00',
        ]);
        $this->assertSame(31, $one['installment_count']);
        $this->assertSame('1.00000000', $one['sum']);

        $this->expectException(GeneratorConstraintViolation::class);
        $this->generator->generate([
            'total_amount' => '0.01',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'schedule_seed' => 'too-small',
        ]);
    }

    public function test_min_max_impossible_fails(): void
    {
        $this->expectException(GeneratorConstraintViolation::class);
        $this->generator->generate([
            'total_amount' => '300.00',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'min_installment' => '20.00',
            'max_installment' => '50.00',
            'schedule_seed' => 'impossible-min',
        ]);
    }

    public function test_preview_does_not_mutate_db(): void
    {
        $beforePlans = PartnerAccrualPlan::query()->count();
        $beforeInst = PartnerAccrualInstallment::query()->count();
        $p = $this->preview->preview([
            'user_id' => 1,
            'total_amount' => '50.00',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-10',
            'schedule_seed' => 'preview-seed',
        ]);
        $this->assertFalse($p['mutates_db']);
        $this->assertSame('50.00000000', $p['sum']);
        $this->assertSame($beforePlans, PartnerAccrualPlan::query()->count());
        $this->assertSame($beforeInst, PartnerAccrualInstallment::query()->count());
    }

    public function test_persist_atomic_and_seed_stable(): void
    {
        $user = $this->makeUser();
        UserBalance::create(['id_user' => $user->id, 'id_code_currency' => 8, 'balance' => 0]);

        $plan = $this->lifecycle->create([
            'user_id' => $user->id,
            'currency_code' => 'USD',
            'currency_code_id' => 8,
            'total_amount' => '30.00',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-03',
            'schedule_seed' => 'stable-seed-abc',
            'created_by_admin_id' => 7,
        ], activate: true);

        $this->assertSame(PartnerAccrualStatus::ACTIVE, $plan->status);
        $this->assertSame(3, $plan->installments()->count());
        $this->assertSame('stable-seed-abc', $plan->schedule_seed);

        $amounts = $plan->installments()->orderBy('sequence')->pluck('amount')->all();
        $regen = $this->generator->generate([
            'total_amount' => '30.00',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-03',
            'schedule_seed' => 'stable-seed-abc',
        ]);
        $this->assertSame(
            array_map(fn ($r) => $r['amount'], $regen['schedule']),
            array_map(fn ($a) => AccrualMoney::scale8(AccrualMoney::of($a)), $amounts)
        );

        $this->assertSame(1, PartnerAccrualPlanEvent::query()->where('plan_id', $plan->id)->where('event_type', 'plan_created_active')->count());
    }

    public function test_overlap_prevention(): void
    {
        $user = $this->makeUser();
        $this->lifecycle->create([
            'user_id' => $user->id,
            'total_amount' => '10.00',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-10',
            'schedule_seed' => 'ov1',
        ]);
        $this->expectException(OverlappingPlanExists::class);
        $this->lifecycle->create([
            'user_id' => $user->id,
            'total_amount' => '10.00',
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-15',
            'schedule_seed' => 'ov2',
        ]);
    }

    public function test_dry_run_zero_mutations(): void
    {
        $plan = $this->seedActivePlan('90.00', '2026-10-01', '2026-10-03');
        $balBefore = (float) UserBalance::query()->where('id_user', $plan->user_id)->value('balance');
        $auditBefore = UserBalanceLog::query()->count();
        $pendingBefore = PartnerAccrualInstallment::query()->where('status', 'PENDING')->count();

        $result = $this->runner->run(dryRun: true, businessDate: '2026-10-03');
        $this->assertTrue($result['dry_run']);
        $this->assertSame(0, $result['balance_mutations']);
        $this->assertSame(0, $result['installment_mutations']);
        $this->assertSame(0, $result['audit_mutations']);
        $this->assertSame(3, $result['would_credit_count']);
        $this->assertSame($balBefore, (float) UserBalance::query()->where('id_user', $plan->user_id)->value('balance'));
        $this->assertSame($auditBefore, UserBalanceLog::query()->count());
        $this->assertSame($pendingBefore, PartnerAccrualInstallment::query()->where('status', 'PENDING')->count());
    }

    public function test_catchup_oldest_first_and_batch_limit(): void
    {
        $plan = $this->seedActivePlan('90.00', '2026-10-01', '2026-10-03');
        config(['partner_accrual.enabled' => true, 'partner_accrual.allow_apply' => true]);

        $r1 = $this->runner->run(dryRun: false, limit: 1, businessDate: '2026-10-03');
        $this->assertSame(1, $r1['credited_count']);
        $this->assertSame(2, $r1['overdue_remaining']);
        $first = PartnerAccrualInstallment::query()->where('plan_id', $plan->id)->where('status', 'CREDITED')->first();
        $this->assertSame('2026-10-01', $first->scheduled_for->toDateString());

        $r2 = $this->runner->run(dryRun: false, limit: 50, businessDate: '2026-10-03');
        $this->assertSame(2, $r2['credited_count']);
        $this->assertSame(0, $r2['overdue_remaining']);
    }

    public function test_pause_resume_cancel(): void
    {
        $plan = $this->seedActivePlan('20.00', '2026-10-01', '2026-10-02');
        $this->lifecycle->pause((int) $plan->id, 1, 'manual');
        $plan->refresh();
        $this->assertSame(PartnerAccrualStatus::PAUSED, $plan->status);

        $dry = $this->runner->run(dryRun: true, businessDate: '2026-10-02', planId: (int) $plan->id);
        $this->assertSame(0, $dry['due_installments']);

        $this->lifecycle->resume((int) $plan->id, 1);
        $plan->refresh();
        $this->assertSame(PartnerAccrualStatus::ACTIVE, $plan->status);
        $seedBefore = $plan->schedule_seed;
        $amountsBefore = $plan->installments()->orderBy('sequence')->pluck('amount')->all();

        config(['partner_accrual.enabled' => true]);
        $this->runner->run(dryRun: false, businessDate: '2026-10-01', planId: (int) $plan->id);
        $plan->refresh();
        $this->assertSame($seedBefore, $plan->schedule_seed);
        $this->assertSame($amountsBefore, $plan->installments()->orderBy('sequence')->pluck('amount')->all());

        $this->lifecycle->cancel((int) $plan->id, 1);
        $plan->refresh();
        $this->assertSame(PartnerAccrualStatus::CANCELLED, $plan->status);
        $pending = PartnerAccrualInstallment::query()->where('plan_id', $plan->id)->where('status', 'PENDING')->count();
        $this->assertSame(0, $pending);
        $credited = PartnerAccrualInstallment::query()->where('plan_id', $plan->id)->where('status', 'CREDITED')->count();
        $this->assertGreaterThanOrEqual(1, $credited);
        $this->assertSame(1, PartnerAccrualPlanEvent::query()->where('event_type', 'plan_cancelled')->count());
    }

    public function test_user_ineligible_auto_pause_no_auto_resume(): void
    {
        $plan = $this->seedActivePlan('10.00', '2026-10-01', '2026-10-01');
        User::query()->whereKey($plan->user_id)->update(['banned_at' => now()]);
        config(['partner_accrual.enabled' => true]);

        $r = $this->runner->run(dryRun: false, businessDate: '2026-10-01', planId: (int) $plan->id);
        $this->assertSame(1, $r['paused_user_ineligible']);
        $plan->refresh();
        $this->assertSame(PartnerAccrualStatus::PAUSED, $plan->status);
        $this->assertSame('USER_NOT_ELIGIBLE', $plan->error_code);
        $this->assertSame(0, (int) UserBalance::query()->where('id_user', $plan->user_id)->value('balance'));
        $this->assertSame(1, PartnerAccrualPlanEvent::query()->where('event_type', 'plan_paused_user_ineligible')->count());
    }

    public function test_job_retry_idempotent(): void
    {
        $plan = $this->seedActivePlan('15.00', '2026-10-01', '2026-10-01');
        $inst = $plan->installments()->first();
        config(['partner_accrual.enabled' => true]);

        $job = new ProcessPartnerAccrualInstallmentJob((int) $inst->id);
        $job->handle($this->credit, $this->lifecycle);
        $job->handle($this->credit, $this->lifecycle); // retry after "crash"

        $this->assertSame(1, UserBalanceLog::query()->where('source', PartnerAccrualCurrency::AUDIT_SOURCE)->count());
        $inst->refresh();
        $this->assertSame(PartnerAccrualInstallmentStatus::CREDITED, $inst->status);
        $second = $this->credit->creditInstallment((int) $inst->id);
        $this->assertSame(CreditResult::ALREADY_CREDITED, $second->outcome);
    }

    public function test_process_restart_replay(): void
    {
        $plan = $this->seedActivePlan('45.00', '2026-10-01', '2026-10-03');
        config(['partner_accrual.enabled' => true]);
        $this->runner->run(dryRun: false, limit: 2, businessDate: '2026-10-03', planId: (int) $plan->id);

        $creditedIds = PartnerAccrualInstallment::query()
            ->where('plan_id', $plan->id)
            ->where('status', 'CREDITED')
            ->pluck('id')
            ->all();
        $this->assertCount(2, $creditedIds);

        // "Restart" — rerun due
        $r = $this->runner->run(dryRun: false, businessDate: '2026-10-03', planId: (int) $plan->id);
        $this->assertSame(1, $r['credited_count']);
        $this->assertSame(3, PartnerAccrualInstallment::query()->where('plan_id', $plan->id)->where('status', 'CREDITED')->count());
        $this->assertSame(3, UserBalanceLog::query()->where('source', PartnerAccrualCurrency::AUDIT_SOURCE)->count());
    }

    public function test_withdrawal_and_admin_set_with_runner(): void
    {
        $plan = $this->seedActivePlan('40.00', '2026-10-01', '2026-10-01', opening: '100.00');
        config(['partner_accrual.enabled' => true]);
        $this->runner->run(dryRun: false, businessDate: '2026-10-01', planId: (int) $plan->id);

        DB::transaction(function () use ($plan) {
            DB::table('user_balance')->where('id_user', $plan->user_id)->lockForUpdate()->first();
            DB::table('user_balance')->where('id_user', $plan->user_id)->update(['balance' => 0]);
        });
        $this->assertSame(0.0, (float) UserBalance::query()->where('id_user', $plan->user_id)->value('balance'));

        $plan2 = $this->seedActivePlan('10.00', '2026-11-01', '2026-11-01', opening: '50.00');
        (new AdminPartnerBalanceAbsoluteSet())->setAbsolute((int) $plan2->user_id, '5.00', 99);
        config(['partner_accrual.enabled' => true]);
        $r = $this->runner->run(dryRun: false, businessDate: '2026-11-01', planId: (int) $plan2->id);
        $this->assertSame(1, $r['credited_count']);
        $this->assertEqualsWithDelta(15.0, (float) UserBalance::query()->where('id_user', $plan2->user_id)->value('balance'), 0.0001);
    }

    public function test_completion_and_reconciliation(): void
    {
        $plan = $this->seedActivePlan('12.00', '2026-10-01', '2026-10-02');
        config(['partner_accrual.enabled' => true]);
        $this->runner->run(dryRun: false, businessDate: '2026-10-02', planId: (int) $plan->id);
        $plan->refresh();
        $this->assertSame(PartnerAccrualStatus::COMPLETED, $plan->status);
        $recon = (new PartnerAccrualReconciliationService())->reconcilePlan((int) $plan->id);
        $this->assertSame(PartnerAccrualReconciliationService::CONSISTENT, $recon['status']);
        $this->assertFalse($recon['auto_repair']);
    }

    public function test_feature_flags_fail_closed(): void
    {
        $this->assertFalse(config('partner_accrual.enabled'));
        $this->assertFalse(config('partner_accrual.scheduler_enabled'));
        $plan = $this->seedActivePlan('5.00', '2026-10-01', '2026-10-01');
        $r = $this->runner->run(dryRun: false, businessDate: '2026-10-01', planId: (int) $plan->id);
        $this->assertSame('FEATURE_DISABLED', $r['installments'][0]['outcome']);
        $this->assertSame(0, $r['credited_count']);

        $obs = (new PartnerAccrualObservability())->snapshot();
        $this->assertArrayHasKey('active_plans', $obs);
        $this->assertArrayHasKey('overdue_installments', $obs);
        $this->assertArrayHasKey('failed_installments_24h', $obs);
        $this->assertArrayHasKey('reconciliation_mismatches', $obs);
        $this->assertFalse($obs['scheduler_enabled']);
    }

    public function test_restart_does_not_change_persisted_schedule(): void
    {
        $plan = $this->seedActivePlan('25.00', '2026-10-01', '2026-10-05', seed: 'fixed-restart');
        $snap = $plan->installments()->orderBy('sequence')->get(['sequence', 'scheduled_for', 'amount'])->toArray();
        // Simulate process restart by new service instances
        $again = new PartnerAccrualPlanLifecycleService();
        $loaded = PartnerAccrualPlan::query()->with('installments')->find($plan->id);
        $snap2 = $loaded->installments()->orderBy('sequence')->get(['sequence', 'scheduled_for', 'amount'])->toArray();
        $this->assertSame(
            array_map(fn ($r) => [$r['sequence'], (string) $r['amount']], $snap),
            array_map(fn ($r) => [$r['sequence'], (string) $r['amount']], $snap2)
        );
        unset($again);
    }

    private function seedActivePlan(
        string $total,
        string $start,
        string $end,
        string $opening = '0',
        string $seed = 'test-seed',
    ): PartnerAccrualPlan {
        $user = $this->makeUser();
        UserBalance::create([
            'id_user' => $user->id,
            'id_code_currency' => 8,
            'balance' => AccrualMoney::toLegacyBalanceScalar(AccrualMoney::of($opening)),
        ]);

        return $this->lifecycle->create([
            'user_id' => $user->id,
            'currency_code' => 'USD',
            'currency_code_id' => 8,
            'total_amount' => $total,
            'start_date' => $start,
            'end_date' => $end,
            'schedule_seed' => $seed.'-'.$user->id,
            'created_by_admin_id' => 1,
        ], activate: true);
    }

    /** @param array<string, mixed> $attrs */
    private function makeUser(array $attrs = []): User
    {
        $id = DB::table('users')->insertGetId(array_merge([
            'email' => 'wave-b-'.uniqid('', true).'@test.local',
            'name' => 'Wave B Test',
            'banned_at' => null,
            'deactivation' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));

        return User::query()->findOrFail($id);
    }
}
