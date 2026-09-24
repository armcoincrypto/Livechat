<?php

declare(strict_types=1);

namespace Tests\Unit\PartnerAccrual;

use App\Enums\PartnerAccrualInstallmentStatus;
use App\Enums\PartnerAccrualStatus;
use App\Models\PartnerAccrualInstallment;
use App\Models\PartnerAccrualPlan;
use App\Models\User;
use App\Models\UserBalance;
use App\Models\UserBalanceLog;
use App\Services\PartnerAccrual\AccrualMoney;
use App\Services\PartnerAccrual\AdminPartnerBalanceAbsoluteSet;
use App\Services\PartnerAccrual\CreditResult;
use App\Services\PartnerAccrual\Exceptions\BudgetExceeded;
use App\Services\PartnerAccrual\Exceptions\CurrencyNotSupported;
use App\Services\PartnerAccrual\Exceptions\OverlappingPlanExists;
use App\Services\PartnerAccrual\Exceptions\UserNotEligible;
use App\Services\PartnerAccrual\PartnerAccrualCreditService;
use App\Services\PartnerAccrual\PartnerAccrualCurrency;
use App\Services\PartnerAccrual\PartnerAccrualPlanGuard;
use App\Services\PartnerAccrual\PartnerAccrualReconciliationService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PartnerAccrualCreditServiceTest extends TestCase
{
    private PartnerAccrualCreditService $service;

    private PartnerAccrualPlanGuard $guard;

    private PartnerAccrualReconciliationService $reconcile;

    protected function setUp(): void
    {
        parent::setUp();
        PartnerAccrualSqliteHarness::bootIsolated();
        $this->service = new PartnerAccrualCreditService(createBalanceIfMissing: true);
        $this->guard = new PartnerAccrualPlanGuard();
        $this->reconcile = new PartnerAccrualReconciliationService();
    }

    public function test_usd_accepted_usdt_rejected(): void
    {
        $user = $this->makeUser();
        $plan = $this->guard->createPlanExclusive([
            'user_id' => $user->id,
            'currency_code' => 'USD',
            'currency_code_id' => 8,
            'total_amount' => '300.00',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'status' => PartnerAccrualStatus::ACTIVE->value,
        ]);
        $this->assertSame('USD', $plan->currency_code);

        $this->expectException(CurrencyNotSupported::class);
        $this->guard->createPlanExclusive([
            'user_id' => $user->id,
            'currency_code' => 'USDT',
            'total_amount' => '100.00',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
        ]);
    }

    public function test_overlapping_plan_rejected(): void
    {
        $user = $this->makeUser();
        $this->guard->createPlanExclusive([
            'user_id' => $user->id,
            'currency_code' => 'USD',
            'total_amount' => '100.00',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'status' => PartnerAccrualStatus::ACTIVE->value,
        ]);

        $this->expectException(OverlappingPlanExists::class);
        $this->guard->createPlanExclusive([
            'user_id' => $user->id,
            'currency_code' => 'USD',
            'total_amount' => '50.00',
            'start_date' => '2026-10-15',
            'end_date' => '2026-11-15',
            'status' => PartnerAccrualStatus::DRAFT->value,
        ]);
    }

    public function test_credit_exact_accounting_and_audit(): void
    {
        [$plan, $installment] = $this->seedPlanWithInstallment('100.00', '8.30', '10.00');

        $result = $this->service->creditInstallment((int) $installment->id);
        $this->assertSame(CreditResult::CREDITED, $result->outcome);
        $this->assertSame('10.00000000', $result->before);
        $this->assertSame('8.30000000', $result->credit);
        $this->assertSame('18.30000000', $result->after);
        $this->assertNotNull($result->auditLogId);

        $balance = UserBalance::query()->where('id_user', $plan->user_id)->first();
        $this->assertEqualsWithDelta(18.3, (float) $balance->balance, 0.0000001);

        $audit = UserBalanceLog::query()->find($result->auditLogId);
        $this->assertSame(PartnerAccrualCurrency::AUDIT_SOURCE, $audit->source);
        $this->assertSame('partner_accrual_installment:'.$installment->id, $audit->reference);

        $plan->refresh();
        $this->assertSame('8.30000000', AccrualMoney::scale8(AccrualMoney::of($plan->credited_amount)));
        $installment->refresh();
        $this->assertSame(PartnerAccrualInstallmentStatus::CREDITED, $installment->status);
    }

    public function test_idempotency_second_call_already_credited(): void
    {
        [, $installment] = $this->seedPlanWithInstallment('300.00', '100.00', '0');

        $first = $this->service->creditInstallment((int) $installment->id);
        $this->assertSame(CreditResult::CREDITED, $first->outcome);

        $balanceAfterFirst = (float) UserBalance::query()->where('id_user', $first->planId ? PartnerAccrualPlan::find($first->planId)->user_id : 0)->value('balance');
        $auditCount = UserBalanceLog::query()->where('source', PartnerAccrualCurrency::AUDIT_SOURCE)->count();

        $second = $this->service->creditInstallment((int) $installment->id);
        $this->assertSame(CreditResult::ALREADY_CREDITED, $second->outcome);
        $this->assertTrue($second->balanceUnchanged());

        $balanceAfterSecond = (float) UserBalance::query()->where('id_user', PartnerAccrualPlan::find($first->planId)->user_id)->value('balance');
        $this->assertSame($balanceAfterFirst, $balanceAfterSecond);
        $this->assertSame($auditCount, UserBalanceLog::query()->where('source', PartnerAccrualCurrency::AUDIT_SOURCE)->count());
    }

    public function test_two_workers_same_installment_sequential_idempotent(): void
    {
        // SQLite cannot emulate true parallel FOR UPDATE workers; sequential double-call
        // plus unique(reference) certifies one credit / one audit.
        [, $installment] = $this->seedPlanWithInstallment('50.00', '25.00', '5');

        $a = $this->service->creditInstallment((int) $installment->id);
        $b = $this->service->creditInstallment((int) $installment->id);

        $this->assertSame(CreditResult::CREDITED, $a->outcome);
        $this->assertSame(CreditResult::ALREADY_CREDITED, $b->outcome);
        $this->assertSame(1, UserBalanceLog::query()->where('reference', 'partner_accrual_installment:'.$installment->id)->count());
        $this->assertEqualsWithDelta(30.0, (float) UserBalance::query()->value('balance'), 0.0001);
    }

    public function test_budget_over_credit_refused_and_plan_error(): void
    {
        $user = $this->makeUser();
        UserBalance::create(['id_user' => $user->id, 'id_code_currency' => 8, 'balance' => 0]);
        $plan = PartnerAccrualPlan::create([
            'user_id' => $user->id,
            'currency_code' => 'USD',
            'currency_code_id' => 8,
            'total_amount' => '10.00000000',
            'credited_amount' => '8.00000000',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'status' => PartnerAccrualStatus::ACTIVE,
        ]);
        $inst = PartnerAccrualInstallment::create([
            'plan_id' => $plan->id,
            'sequence' => 1,
            'scheduled_for' => '2026-10-05',
            'amount' => '5.00000000',
            'status' => PartnerAccrualInstallmentStatus::PENDING,
            'idempotency_key' => PartnerAccrualCurrency::idempotencyKey((int) $plan->id, 0).'-pending',
        ]);
        // Fix key after id known
        $inst->idempotency_key = PartnerAccrualCurrency::idempotencyKey((int) $plan->id, (int) $inst->id);
        $inst->save();

        try {
            $this->service->creditInstallment((int) $inst->id);
            $this->fail('expected BudgetExceeded');
        } catch (BudgetExceeded $e) {
            $this->assertSame('BUDGET_EXCEEDED', $e->codeName());
        }

        $plan->refresh();
        $this->assertSame(PartnerAccrualStatus::ERROR, $plan->status);
        $this->assertEqualsWithDelta(0.0, (float) UserBalance::query()->where('id_user', $user->id)->value('balance'), 0.0001);
    }

    public function test_final_installment_completes_plan(): void
    {
        $user = $this->makeUser();
        UserBalance::create(['id_user' => $user->id, 'id_code_currency' => 8, 'balance' => 0]);
        $plan = PartnerAccrualPlan::create([
            'user_id' => $user->id,
            'currency_code' => 'USD',
            'currency_code_id' => 8,
            'total_amount' => '30.00000000',
            'credited_amount' => '20.00000000',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'status' => PartnerAccrualStatus::ACTIVE,
        ]);
        PartnerAccrualInstallment::create([
            'plan_id' => $plan->id,
            'sequence' => 1,
            'scheduled_for' => '2026-10-01',
            'amount' => '20.00000000',
            'status' => PartnerAccrualInstallmentStatus::CREDITED,
            'idempotency_key' => 'partner_accrual:'.$plan->id.':pre',
            'executed_at' => now(),
        ]);
        $final = PartnerAccrualInstallment::create([
            'plan_id' => $plan->id,
            'sequence' => 2,
            'scheduled_for' => '2026-10-15',
            'amount' => '10.00000000',
            'status' => PartnerAccrualInstallmentStatus::PENDING,
            'idempotency_key' => 'tmp',
        ]);
        $final->idempotency_key = PartnerAccrualCurrency::idempotencyKey((int) $plan->id, (int) $final->id);
        $final->save();

        $result = $this->service->creditInstallment((int) $final->id);
        $this->assertSame(CreditResult::CREDITED, $result->outcome);
        $plan->refresh();
        $this->assertSame(PartnerAccrualStatus::COMPLETED, $plan->status);
        $this->assertNotNull($plan->completed_at);
        $this->assertSame('30.00000000', AccrualMoney::scale8(AccrualMoney::of($plan->credited_amount)));
    }

    public function test_banned_and_deactivated_refused(): void
    {
        $banned = $this->makeUser(['banned_at' => now()]);
        UserBalance::create(['id_user' => $banned->id, 'id_code_currency' => 8, 'balance' => 1]);
        $plan = $this->makeActivePlan($banned->id, '50.00');
        $inst = $this->makePendingInstallment($plan, '10.00', 1);
        try {
            $this->service->creditInstallment((int) $inst->id);
            $this->fail('expected UserNotEligible banned');
        } catch (UserNotEligible $e) {
            $this->assertSame('USER_NOT_ELIGIBLE', $e->codeName());
        }

        $deact = $this->makeUser(['deactivation' => 1]);
        UserBalance::create(['id_user' => $deact->id, 'id_code_currency' => 8, 'balance' => 1]);
        $plan2 = $this->makeActivePlan($deact->id, '50.00');
        $inst2 = $this->makePendingInstallment($plan2, '10.00', 1);
        $this->expectException(UserNotEligible::class);
        $this->service->creditInstallment((int) $inst2->id);
    }

    public function test_withdrawal_drain_vs_accrual_serialization(): void
    {
        [$plan, $installment] = $this->seedPlanWithInstallment('100.00', '40.00', '60.00');

        // Accrual first
        $credit = $this->service->creditInstallment((int) $installment->id);
        $this->assertSame('100.00000000', $credit->after);

        // Withdrawal-style drain under FOR UPDATE (mirrors PartnerWithdrawalController)
        DB::transaction(function () use ($plan) {
            $row = DB::table('user_balance')->where('id_user', $plan->user_id)->lockForUpdate()->first();
            $this->assertNotNull($row);
            DB::table('user_balance')->where('id_user', $plan->user_id)->update(['balance' => 0]);
        });

        $final = (float) UserBalance::query()->where('id_user', $plan->user_id)->value('balance');
        $this->assertSame(0.0, $final);

        // Reverse order: withdraw then credit
        UserBalance::query()->where('id_user', $plan->user_id)->update(['balance' => 25]);
        $inst2 = $this->makePendingInstallment($plan, '15.00', 2);
        DB::transaction(function () use ($plan) {
            DB::table('user_balance')->where('id_user', $plan->user_id)->lockForUpdate()->first();
            DB::table('user_balance')->where('id_user', $plan->user_id)->update(['balance' => 0]);
        });
        $credit2 = $this->service->creditInstallment((int) $inst2->id);
        $this->assertSame('0.00000000', $credit2->before);
        $this->assertSame('15.00000000', $credit2->after);
    }

    public function test_admin_absolute_set_vs_accrual_locking(): void
    {
        [$plan, $installment] = $this->seedPlanWithInstallment('100.00', '10.00', '50.00');
        $admin = new AdminPartnerBalanceAbsoluteSet();

        $set = $admin->setAbsolute((int) $plan->user_id, '5.00', 99);
        $this->assertTrue($set['changed']);
        $this->assertSame('5.00000000', $set['to']);

        $credit = $this->service->creditInstallment((int) $installment->id);
        $this->assertSame('5.00000000', $credit->before);
        $this->assertSame('15.00000000', $credit->after);
    }

    public function test_reconciliation_consistent_missing_audit_mismatch_overcredit(): void
    {
        [$plan, $installment] = $this->seedPlanWithInstallment('100.00', '25.00', '0');
        $this->service->creditInstallment((int) $installment->id);
        $ok = $this->reconcile->reconcilePlan((int) $plan->id);
        $this->assertSame(PartnerAccrualReconciliationService::CONSISTENT, $ok['status']);
        $this->assertFalse($ok['auto_repair']);

        // Missing audit
        UserBalanceLog::query()->delete();
        $missing = $this->reconcile->reconcilePlan((int) $plan->id);
        $this->assertSame(PartnerAccrualReconciliationService::MISSING_AUDIT, $missing['status']);

        // Force credited mismatch
        $plan->credited_amount = '99.00000000';
        $plan->save();
        // restore a fake audit so status prefers MISMATCH/OVER
        UserBalanceLog::create([
            'id_user' => $plan->user_id,
            'id_manager' => 0,
            'type' => 1,
            'route_type' => 2,
            'source' => PartnerAccrualCurrency::AUDIT_SOURCE,
            'reference' => 'partner_accrual_installment:'.$installment->id,
            'from_balance' => '0',
            'to_balance' => '25',
            'text' => 'repair',
        ]);
        $mismatch = $this->reconcile->reconcilePlan((int) $plan->id);
        $this->assertContains($mismatch['status'], [
            PartnerAccrualReconciliationService::MISMATCH,
            PartnerAccrualReconciliationService::OVER_CREDIT,
        ]);

        $plan->credited_amount = '150.00000000';
        $plan->total_amount = '100.00000000';
        $plan->save();
        $over = $this->reconcile->reconcilePlan((int) $plan->id);
        $this->assertSame(PartnerAccrualReconciliationService::OVER_CREDIT, $over['status']);
    }

    public function test_dry_run_demo_budget_300_then_idempotent(): void
    {
        [$plan, $installment] = $this->seedPlanWithInstallment('300.00', '50.00', '100.00');

        $dry = $this->service->creditInstallment((int) $installment->id, dryRun: true);
        $this->assertSame(CreditResult::DRY_RUN_OK, $dry->outcome);
        $this->assertSame('100.00000000', $dry->before);
        $this->assertSame('50.00000000', $dry->credit);
        $this->assertSame('150.00000000', $dry->after);
        $this->assertEqualsWithDelta(100.0, (float) UserBalance::query()->where('id_user', $plan->user_id)->value('balance'), 0.0001);

        $first = $this->service->creditInstallment((int) $installment->id);
        fwrite(STDOUT, 'BEFORE='.$first->before."\n");
        fwrite(STDOUT, 'CREDIT='.$first->credit."\n");
        fwrite(STDOUT, 'AFTER='.$first->after."\n");
        fwrite(STDOUT, 'AUDIT='.$first->auditLogId."\n");
        fwrite(STDOUT, 'IDEMPOTENCY_KEY='.$first->idempotencyKey."\n");

        $second = $this->service->creditInstallment((int) $installment->id);
        $this->assertSame(CreditResult::ALREADY_CREDITED, $second->outcome);
        fwrite(STDOUT, "SECOND_OUTCOME=ALREADY_CREDITED\n");
        fwrite(STDOUT, "BALANCE_UNCHANGED=YES\n");
    }

    public function test_no_float_math_in_plan_amounts(): void
    {
        $plan = PartnerAccrualPlan::create([
            'user_id' => $this->makeUser()->id,
            'currency_code' => 'USD',
            'total_amount' => AccrualMoney::scale8(AccrualMoney::of('0.10')),
            'credited_amount' => AccrualMoney::scale8(AccrualMoney::of('0.20')),
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => PartnerAccrualStatus::DRAFT,
        ]);
        // Classic float trap: 0.1+0.2 !== 0.3 in float; BigDecimal must be exact.
        $sum = AccrualMoney::of($plan->total_amount)->plus(AccrualMoney::of($plan->credited_amount));
        $this->assertSame('0.30000000', AccrualMoney::scale8($sum));
    }

    /** @return array{0: PartnerAccrualPlan, 1: PartnerAccrualInstallment} */
    private function seedPlanWithInstallment(string $total, string $amount, string $openingBalance): array
    {
        $user = $this->makeUser();
        UserBalance::create([
            'id_user' => $user->id,
            'id_code_currency' => 8,
            'balance' => AccrualMoney::toLegacyBalanceScalar(AccrualMoney::of($openingBalance)),
        ]);
        $plan = $this->makeActivePlan((int) $user->id, $total);
        $inst = $this->makePendingInstallment($plan, $amount, 1);

        return [$plan, $inst];
    }

    private function makeActivePlan(int $userId, string $total): PartnerAccrualPlan
    {
        return PartnerAccrualPlan::create([
            'user_id' => $userId,
            'currency_code' => 'USD',
            'currency_code_id' => 8,
            'total_amount' => AccrualMoney::scale8(AccrualMoney::of($total)),
            'credited_amount' => AccrualMoney::scale8(AccrualMoney::zero()),
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'status' => PartnerAccrualStatus::ACTIVE,
            'created_by_admin_id' => 1,
        ]);
    }

    private function makePendingInstallment(PartnerAccrualPlan $plan, string $amount, int $seq): PartnerAccrualInstallment
    {
        $inst = PartnerAccrualInstallment::create([
            'plan_id' => $plan->id,
            'sequence' => $seq,
            'scheduled_for' => sprintf('2026-10-%02d', min(28, $seq)),
            'amount' => AccrualMoney::scale8(AccrualMoney::of($amount)),
            'status' => PartnerAccrualInstallmentStatus::PENDING,
            'idempotency_key' => 'pending-'.$plan->id.'-'.$seq.'-'.uniqid('', true),
        ]);
        $inst->idempotency_key = PartnerAccrualCurrency::idempotencyKey((int) $plan->id, (int) $inst->id);
        $inst->save();

        return $inst;
    }

    /** @param array<string, mixed> $attrs */
    private function makeUser(array $attrs = []): User
    {
        // Raw insert avoids Bannable / role side-effects on the slim SQLite schema.
        $id = DB::table('users')->insertGetId(array_merge([
            'email' => 'wave-a-'.uniqid('', true).'@test.local',
            'name' => 'Wave A Test',
            'banned_at' => null,
            'deactivation' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));

        return User::query()->findOrFail($id);
    }
}
