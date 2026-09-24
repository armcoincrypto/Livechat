<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual;

use App\Enums\PartnerAccrualInstallmentStatus;
use App\Enums\PartnerAccrualStatus;
use App\Models\PartnerAccrualInstallment;
use App\Models\PartnerAccrualPlan;
use App\Models\User;
use App\Models\UserBalance;
use App\Models\UserBalanceLog;
use App\Services\PartnerAccrual\Exceptions\AccountingInvariantViolation;
use App\Services\PartnerAccrual\Exceptions\BalanceMissing;
use App\Services\PartnerAccrual\Exceptions\BudgetExceeded;
use App\Services\PartnerAccrual\Exceptions\InstallmentAlreadyCredited;
use App\Services\PartnerAccrual\Exceptions\InstallmentNotCreditable;
use App\Services\PartnerAccrual\Exceptions\PlanNotActive;
use App\Services\PartnerAccrual\Exceptions\UserNotEligible;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Narrow financial primitive: idempotent delta-credit for one accrual installment.
 * No scheduler, email, Telegram, ReferralEngine, or withdrawal logic.
 */
final class PartnerAccrualCreditService
{
    /** route_type reserved for partner monthly accrual audits (admin absolute set uses 1). */
    public const AUDIT_ROUTE_TYPE = 2;

    public function __construct(
        private readonly bool $createBalanceIfMissing = true,
    ) {}

    /**
     * @param  bool  $dryRun  When true, validates and reports would-be delta without mutating.
     */
    public function creditInstallment(int $installmentId, bool $dryRun = false): CreditResult
    {
        if ($dryRun) {
            return $this->dryRunInspect($installmentId);
        }

        try {
            $outcome = DB::transaction(function () use ($installmentId) {
                /** @var PartnerAccrualInstallment|null $installment */
                $installment = PartnerAccrualInstallment::query()
                    ->whereKey($installmentId)
                    ->lockForUpdate()
                    ->first();

                if (! $installment) {
                    throw AccountingInvariantViolation::message('installment '.$installmentId.' not found');
                }

                $idempotencyKey = $installment->ensureIdempotencyKey();
                if ($installment->isDirty('idempotency_key')) {
                    $installment->save();
                }

                if ($installment->status === PartnerAccrualInstallmentStatus::CREDITED) {
                    return new CreditResult(
                        outcome: CreditResult::ALREADY_CREDITED,
                        installmentId: (int) $installment->id,
                        planId: (int) $installment->plan_id,
                        before: null,
                        credit: AccrualMoney::scale8(AccrualMoney::of($installment->amount)),
                        after: null,
                        auditLogId: $installment->balance_log_id ? (int) $installment->balance_log_id : null,
                        idempotencyKey: $idempotencyKey,
                        planStatus: null,
                        message: 'ALREADY_CREDITED',
                    );
                }

                if (! $installment->status->canCredit()) {
                    throw InstallmentNotCreditable::forStatus(
                        (int) $installment->id,
                        $installment->status->value
                    );
                }

                /** @var PartnerAccrualPlan $plan */
                $plan = PartnerAccrualPlan::query()
                    ->whereKey($installment->plan_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                PartnerAccrualCurrency::assertSupported((string) $plan->currency_code);

                if ($plan->status !== PartnerAccrualStatus::ACTIVE) {
                    throw PlanNotActive::forStatus($plan->status->value);
                }

                $user = User::query()->whereKey($plan->user_id)->lockForUpdate()->first();
                if (! $user) {
                    throw AccountingInvariantViolation::message('user '.$plan->user_id.' missing');
                }
                $this->assertUserEligible($user);

                $credit = AccrualMoney::of($installment->amount);
                if (AccrualMoney::cmp($credit, AccrualMoney::zero()) <= 0) {
                    throw AccountingInvariantViolation::message('installment amount must be positive');
                }

                $total = AccrualMoney::of($plan->total_amount);
                $credited = AccrualMoney::of($plan->credited_amount);
                $remaining = $total->minus($credited);

                if (AccrualMoney::cmp($credit, $remaining) > 0) {
                    // Persist ERROR/FAILED inside this transaction, then signal throw after commit
                    // so the operator-visible plan state is not rolled back with the exception.
                    $plan->status = PartnerAccrualStatus::ERROR;
                    $plan->error_code = 'BUDGET_EXCEEDED';
                    $plan->error_message = 'Installment '.$installment->id.' amount exceeds remaining budget.';
                    $plan->save();

                    $installment->status = PartnerAccrualInstallmentStatus::FAILED;
                    $installment->attempt_count = (int) $installment->attempt_count + 1;
                    $installment->last_error = 'BUDGET_EXCEEDED';
                    $installment->save();

                    return [
                        '_throw' => BudgetExceeded::forAmounts(
                            AccrualMoney::scale8($credit),
                            AccrualMoney::scale8($remaining)
                        ),
                    ];
                }

                $installment->status = PartnerAccrualInstallmentStatus::PROCESSING;
                $installment->attempt_count = (int) $installment->attempt_count + 1;
                $installment->save();

                $balanceRow = $this->lockOrCreateBalance((int) $plan->user_id, $plan->currency_code_id);
                $before = AccrualMoney::fromLegacyBalance($balanceRow->balance);
                $after = $before->plus($credit);

                // Idempotency at audit layer: unique reference.
                $reference = PartnerAccrualCurrency::installmentReference((int) $installment->id);
                $existingAudit = UserBalanceLog::query()
                    ->where('reference', $reference)
                    ->lockForUpdate()
                    ->first();

                if ($existingAudit) {
                    // Repair installment pointer if prior crash left CREDITED audit.
                    $installment->status = PartnerAccrualInstallmentStatus::CREDITED;
                    $installment->balance_log_id = (int) $existingAudit->id;
                    $installment->executed_at = $installment->executed_at ?? now();
                    $installment->last_error = null;
                    $installment->save();

                    return new CreditResult(
                        outcome: CreditResult::ALREADY_CREDITED,
                        installmentId: (int) $installment->id,
                        planId: (int) $plan->id,
                        before: AccrualMoney::scale8(AccrualMoney::of((string) $existingAudit->from_balance)),
                        credit: AccrualMoney::scale8($credit),
                        after: AccrualMoney::scale8(AccrualMoney::of((string) $existingAudit->to_balance)),
                        auditLogId: (int) $existingAudit->id,
                        idempotencyKey: $idempotencyKey,
                        planStatus: $plan->status->value,
                        message: 'ALREADY_CREDITED',
                    );
                }

                $balanceRow->balance = AccrualMoney::toLegacyBalanceScalar($after);
                $balanceRow->save();

                $audit = UserBalanceLog::create([
                    'id_user' => (int) $plan->user_id,
                    'id_manager' => 0,
                    'type' => 1,
                    'route_type' => self::AUDIT_ROUTE_TYPE,
                    'source' => PartnerAccrualCurrency::AUDIT_SOURCE,
                    'reference' => $reference,
                    'text' => sprintf(
                        '[%s] +%s USD — %s key=%s',
                        PartnerAccrualCurrency::AUDIT_SOURCE,
                        AccrualMoney::display2($credit),
                        $reference,
                        $idempotencyKey
                    ),
                    'from_balance' => AccrualMoney::scale8($before),
                    'to_balance' => AccrualMoney::scale8($after),
                ]);

                $newCredited = $credited->plus($credit);
                $plan->credited_amount = AccrualMoney::scale8($newCredited);

                $installment->status = PartnerAccrualInstallmentStatus::CREDITED;
                $installment->balance_log_id = (int) $audit->id;
                $installment->executed_at = now();
                $installment->last_error = null;
                $installment->idempotency_key = $idempotencyKey;
                $installment->save();

                $this->applyCompletionInvariant($plan, $newCredited, $total);
                $plan->save();

                return new CreditResult(
                    outcome: CreditResult::CREDITED,
                    installmentId: (int) $installment->id,
                    planId: (int) $plan->id,
                    before: AccrualMoney::scale8($before),
                    credit: AccrualMoney::scale8($credit),
                    after: AccrualMoney::scale8($after),
                    auditLogId: (int) $audit->id,
                    idempotencyKey: $idempotencyKey,
                    planStatus: $plan->status->value,
                    message: null,
                );
            });

            if (is_array($outcome) && isset($outcome['_throw']) && $outcome['_throw'] instanceof Throwable) {
                throw $outcome['_throw'];
            }

            /** @var CreditResult $outcome */
            return $outcome;
        } catch (InstallmentAlreadyCredited $e) {
            return new CreditResult(
                outcome: CreditResult::ALREADY_CREDITED,
                installmentId: $installmentId,
                message: $e->getMessage(),
            );
        }
    }

    public function inspectInstallment(int $installmentId): array
    {
        $installment = PartnerAccrualInstallment::query()->find($installmentId);
        if (! $installment) {
            return ['found' => false];
        }

        $plan = PartnerAccrualPlan::query()->find($installment->plan_id);

        return [
            'found' => true,
            'installment_id' => (int) $installment->id,
            'plan_id' => (int) $installment->plan_id,
            'status' => $installment->status->value,
            'amount' => AccrualMoney::scale8(AccrualMoney::of($installment->amount)),
            'idempotency_key' => $installment->idempotency_key,
            'balance_log_id' => $installment->balance_log_id,
            'plan_status' => $plan?->status->value,
            'plan_credited_amount' => $plan ? AccrualMoney::scale8(AccrualMoney::of($plan->credited_amount)) : null,
            'plan_total_amount' => $plan ? AccrualMoney::scale8(AccrualMoney::of($plan->total_amount)) : null,
            'currency_code' => $plan?->currency_code,
        ];
    }

    private function dryRunInspect(int $installmentId): CreditResult
    {
        $installment = PartnerAccrualInstallment::query()->find($installmentId);
        if (! $installment) {
            throw AccountingInvariantViolation::message('installment '.$installmentId.' not found');
        }

        if ($installment->status === PartnerAccrualInstallmentStatus::CREDITED) {
            return new CreditResult(
                outcome: CreditResult::ALREADY_CREDITED,
                installmentId: (int) $installment->id,
                planId: (int) $installment->plan_id,
                credit: AccrualMoney::scale8(AccrualMoney::of($installment->amount)),
                auditLogId: $installment->balance_log_id ? (int) $installment->balance_log_id : null,
                idempotencyKey: (string) $installment->idempotency_key,
                message: 'ALREADY_CREDITED',
            );
        }

        $plan = PartnerAccrualPlan::query()->findOrFail($installment->plan_id);
        PartnerAccrualCurrency::assertSupported((string) $plan->currency_code);
        if ($plan->status !== PartnerAccrualStatus::ACTIVE) {
            throw PlanNotActive::forStatus($plan->status->value);
        }

        $user = User::query()->findOrFail($plan->user_id);
        $this->assertUserEligible($user);

        $credit = AccrualMoney::of($installment->amount);
        $remaining = AccrualMoney::of($plan->total_amount)->minus(AccrualMoney::of($plan->credited_amount));
        if (AccrualMoney::cmp($credit, $remaining) > 0) {
            throw BudgetExceeded::forAmounts(AccrualMoney::scale8($credit), AccrualMoney::scale8($remaining));
        }

        $balance = UserBalance::query()->where('id_user', $plan->user_id)->first();
        $before = AccrualMoney::fromLegacyBalance($balance?->balance);

        return new CreditResult(
            outcome: CreditResult::DRY_RUN_OK,
            installmentId: (int) $installment->id,
            planId: (int) $plan->id,
            before: AccrualMoney::scale8($before),
            credit: AccrualMoney::scale8($credit),
            after: AccrualMoney::scale8($before->plus($credit)),
            idempotencyKey: (string) ($installment->idempotency_key
                ?: PartnerAccrualCurrency::idempotencyKey((int) $plan->id, (int) $installment->id)),
            planStatus: $plan->status->value,
            message: 'DRY_RUN — no balance mutation',
        );
    }

    private function assertUserEligible(User $user): void
    {
        if ($user->banned_at !== null) {
            throw UserNotEligible::banned((int) $user->id);
        }
        if ((int) ($user->deactivation ?? 0) === 1) {
            throw UserNotEligible::deactivated((int) $user->id);
        }
    }

    private function lockOrCreateBalance(int $userId, ?int $currencyCodeId): UserBalance
    {
        $row = UserBalance::query()
            ->where('id_user', $userId)
            ->lockForUpdate()
            ->first();

        if ($row) {
            return $row;
        }

        if (! $this->createBalanceIfMissing) {
            throw BalanceMissing::forUser($userId);
        }

        // Match ReferralEngine::createBalanceIfMissing product semantics.
        $idCode = $currencyCodeId;
        if (! $idCode || $idCode <= 0) {
            $idCode = (int) (function_exists('iEXSetting') ? iEXSetting('id_referral_code_currency') : 8);
        }

        UserBalance::create([
            'id_user' => $userId,
            'id_code_currency' => $idCode,
            'balance' => 0,
            'referral_total_profit' => 0,
            'referral_total_withdrawal' => 0,
        ]);

        $row = UserBalance::query()
            ->where('id_user', $userId)
            ->lockForUpdate()
            ->first();

        if (! $row) {
            throw BalanceMissing::forUser($userId);
        }

        return $row;
    }

    private function applyCompletionInvariant(
        PartnerAccrualPlan $plan,
        \Brick\Math\BigDecimal $newCredited,
        \Brick\Math\BigDecimal $total
    ): void {
        $creditedSum = PartnerAccrualInstallment::query()
            ->where('plan_id', $plan->id)
            ->where('status', PartnerAccrualInstallmentStatus::CREDITED->value)
            ->get(['amount'])
            ->reduce(
                fn ($carry, $row) => $carry->plus(AccrualMoney::of($row->amount)),
                AccrualMoney::zero()
            );

        // Include the installment we just marked CREDITED (already in DB after save).
        if (! AccrualMoney::eq($creditedSum, $newCredited)) {
            // plan.credited_amount is source of truth within this transaction;
            // SUM should match. If not, error the plan.
            $plan->status = PartnerAccrualStatus::ERROR;
            $plan->error_code = 'CREDITED_SUM_MISMATCH';
            $plan->error_message = sprintf(
                'SUM(CREDITED)=%s plan.credited_amount=%s',
                AccrualMoney::scale8($creditedSum),
                AccrualMoney::scale8($newCredited)
            );

            return;
        }

        if (AccrualMoney::eq($newCredited, $total)) {
            $plan->status = PartnerAccrualStatus::COMPLETED;
            $plan->completed_at = now();
            $plan->error_code = null;
            $plan->error_message = null;

            return;
        }

        if (AccrualMoney::cmp($newCredited, $total) > 0) {
            $plan->status = PartnerAccrualStatus::ERROR;
            $plan->error_code = 'OVER_CREDIT';
            $plan->error_message = sprintf(
                'credited_amount %s > total_amount %s',
                AccrualMoney::scale8($newCredited),
                AccrualMoney::scale8($total)
            );
        }
    }
}
