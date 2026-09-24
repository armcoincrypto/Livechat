<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual;

use App\Enums\PartnerAccrualInstallmentStatus;
use App\Enums\PartnerAccrualStatus;
use App\Models\PartnerAccrualInstallment;
use App\Models\PartnerAccrualPlan;
use App\Models\PartnerAccrualPlanEvent;
use App\Models\User;
use App\Services\PartnerAccrual\Exceptions\AccountingInvariantViolation;
use App\Services\PartnerAccrual\Exceptions\PlanNotActive;
use Illuminate\Support\Facades\DB;

/**
 * Plan create/activate/pause/resume/cancel. Never credits balance.
 */
final class PartnerAccrualPlanLifecycleService
{
    public function __construct(
        private readonly PartnerAccrualPlanGenerator $generator = new PartnerAccrualPlanGenerator(),
        private readonly PartnerAccrualPlanGuard $guard = new PartnerAccrualPlanGuard(),
    ) {}

    /**
     * Atomically persist plan + full installment set.
     *
     * @param  array{
     *   user_id: int,
     *   currency_code?: string,
     *   currency_code_id?: int|null,
     *   total_amount: string,
     *   start_date: string,
     *   end_date: string,
     *   status?: string,
     *   created_by_admin_id?: int|null,
     *   schedule_seed?: string|null,
     *   min_installment?: string|null,
     *   max_installment?: string|null,
     *   variance_percent?: int|null,
     *   metadata?: array|null
     * }  $input
     */
    public function create(array $input, bool $activate = true): PartnerAccrualPlan
    {
        $currency = strtoupper(trim((string) ($input['currency_code'] ?? PartnerAccrualCurrency::V1_CODE)));
        PartnerAccrualCurrency::assertSupported($currency);

        $generated = $this->generator->generate([
            'total_amount' => $input['total_amount'],
            'start_date' => $input['start_date'],
            'end_date' => $input['end_date'],
            'currency_code' => $currency,
            'schedule_seed' => $input['schedule_seed'] ?? null,
            'min_installment' => $input['min_installment'] ?? null,
            'max_installment' => $input['max_installment'] ?? null,
            'variance_percent' => $input['variance_percent'] ?? null,
        ]);

        $status = $activate
            ? PartnerAccrualStatus::ACTIVE
            : PartnerAccrualStatus::from($input['status'] ?? PartnerAccrualStatus::DRAFT->value);

        return DB::transaction(function () use ($input, $currency, $generated, $status, $activate) {
            // Overlap guard under lock (ACTIVE/PAUSED/DRAFT blocking).
            $plan = $this->guard->createPlanExclusive([
                'user_id' => (int) $input['user_id'],
                'currency_code' => $currency,
                'currency_code_id' => $input['currency_code_id'] ?? 8,
                'total_amount' => $generated['total_amount'],
                'start_date' => $input['start_date'],
                'end_date' => $input['end_date'],
                'status' => $status->value,
                'created_by_admin_id' => $input['created_by_admin_id'] ?? null,
                'metadata' => $input['metadata'] ?? null,
            ]);

            $plan->schedule_seed = $generated['schedule_seed'];
            $plan->min_installment = $generated['min_installment'];
            $plan->max_installment = $generated['max_installment'];
            $plan->variance_percent = $generated['variance_percent'];
            if ($activate) {
                $plan->activated_at = now();
            }
            $plan->save();

            foreach ($generated['schedule'] as $row) {
                $inst = PartnerAccrualInstallment::create([
                    'plan_id' => $plan->id,
                    'sequence' => $row['sequence'],
                    'scheduled_for' => $row['scheduled_for'],
                    'amount' => $row['amount'],
                    'status' => PartnerAccrualInstallmentStatus::PENDING,
                    'idempotency_key' => 'pending-'.$plan->id.'-'.$row['sequence'],
                ]);
                $inst->idempotency_key = PartnerAccrualCurrency::idempotencyKey((int) $plan->id, (int) $inst->id);
                $inst->save();
            }

            $count = PartnerAccrualInstallment::query()->where('plan_id', $plan->id)->count();
            if ($count !== $generated['installment_count']) {
                throw AccountingInvariantViolation::message(
                    'PLAN_CREATED_WITHOUT_COMPLETE_INSTALLMENT_SET'
                );
            }

            $this->recordEvent($plan, $activate ? 'plan_created_active' : 'plan_created_draft', $input['created_by_admin_id'] ?? null, [
                'installment_count' => $count,
                'sum' => $generated['sum'],
                'schedule_seed' => $generated['schedule_seed'],
            ]);

            return $plan->fresh(['installments']);
        });
    }

    public function activate(int $planId, ?int $actorAdminId = null): PartnerAccrualPlan
    {
        return DB::transaction(function () use ($planId, $actorAdminId) {
            $plan = PartnerAccrualPlan::query()->whereKey($planId)->lockForUpdate()->firstOrFail();
            if ($plan->status !== PartnerAccrualStatus::DRAFT) {
                throw AccountingInvariantViolation::message('only DRAFT plans can be activated (status='.$plan->status->value.')');
            }
            $count = PartnerAccrualInstallment::query()->where('plan_id', $plan->id)->count();
            if ($count < 1) {
                throw AccountingInvariantViolation::message('cannot activate plan without persisted installments');
            }
            $plan->status = PartnerAccrualStatus::ACTIVE;
            $plan->activated_at = now();
            $plan->save();
            $this->recordEvent($plan, 'plan_activated', $actorAdminId, []);

            return $plan->fresh();
        });
    }

    public function pause(int $planId, ?int $actorAdminId = null, string $reason = 'manual'): PartnerAccrualPlan
    {
        return DB::transaction(function () use ($planId, $actorAdminId, $reason) {
            $plan = PartnerAccrualPlan::query()->whereKey($planId)->lockForUpdate()->firstOrFail();
            if ($plan->status !== PartnerAccrualStatus::ACTIVE) {
                throw PlanNotActive::forStatus($plan->status->value);
            }
            $plan->status = PartnerAccrualStatus::PAUSED;
            $plan->paused_at = now();
            $plan->save();
            $this->recordEvent($plan, 'plan_paused', $actorAdminId, ['reason' => $reason]);

            return $plan->fresh();
        });
    }

    public function resume(int $planId, ?int $actorAdminId = null): PartnerAccrualPlan
    {
        return DB::transaction(function () use ($planId, $actorAdminId) {
            $plan = PartnerAccrualPlan::query()->whereKey($planId)->lockForUpdate()->firstOrFail();
            if ($plan->status !== PartnerAccrualStatus::PAUSED) {
                throw AccountingInvariantViolation::message('only PAUSED plans can be resumed');
            }
            // No schedule regeneration — missed PENDING become due under catch-up.
            $plan->status = PartnerAccrualStatus::ACTIVE;
            $plan->paused_at = null;
            $plan->error_code = null;
            $plan->error_message = null;
            $plan->save();
            $this->recordEvent($plan, 'plan_resumed', $actorAdminId, ['schedule_regenerated' => false]);

            return $plan->fresh();
        });
    }

    public function cancel(int $planId, ?int $actorAdminId = null): PartnerAccrualPlan
    {
        return DB::transaction(function () use ($planId, $actorAdminId) {
            $plan = PartnerAccrualPlan::query()->whereKey($planId)->lockForUpdate()->firstOrFail();
            if (! in_array($plan->status, [PartnerAccrualStatus::ACTIVE, PartnerAccrualStatus::PAUSED], true)) {
                throw AccountingInvariantViolation::message('only ACTIVE/PAUSED plans can be cancelled');
            }

            PartnerAccrualInstallment::query()
                ->where('plan_id', $plan->id)
                ->whereIn('status', [
                    PartnerAccrualInstallmentStatus::PENDING->value,
                    PartnerAccrualInstallmentStatus::FAILED->value,
                    PartnerAccrualInstallmentStatus::PROCESSING->value,
                ])
                ->update([
                    'status' => PartnerAccrualInstallmentStatus::CANCELLED->value,
                    'updated_at' => now(),
                ]);

            $plan->status = PartnerAccrualStatus::CANCELLED;
            $plan->cancelled_at = now();
            $plan->save();
            $this->recordEvent($plan, 'plan_cancelled', $actorAdminId, [
                'clawback' => false,
                'credited_amount_unchanged' => true,
            ]);

            return $plan->fresh();
        });
    }

    public function pauseForUserIneligible(int $planId, string $detail): PartnerAccrualPlan
    {
        return DB::transaction(function () use ($planId, $detail) {
            $plan = PartnerAccrualPlan::query()->whereKey($planId)->lockForUpdate()->firstOrFail();
            if ($plan->status !== PartnerAccrualStatus::ACTIVE) {
                return $plan;
            }
            $plan->status = PartnerAccrualStatus::PAUSED;
            $plan->paused_at = now();
            $plan->error_code = 'USER_NOT_ELIGIBLE';
            $plan->error_message = $detail;
            $plan->save();
            $this->recordEvent($plan, 'plan_paused_user_ineligible', null, [
                'detail' => $detail,
                'outcome' => 'PLAN_PAUSED_USER_INELIGIBLE',
            ]);

            return $plan->fresh();
        });
    }

    /** @param array<string, mixed> $payload */
    private function recordEvent(PartnerAccrualPlan $plan, string $type, ?int $actorAdminId, array $payload): void
    {
        PartnerAccrualPlanEvent::create([
            'plan_id' => $plan->id,
            'event_type' => $type,
            'actor_admin_id' => $actorAdminId,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
