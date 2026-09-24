<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual;

use App\Models\PartnerAccrualInstallment;
use App\Models\PartnerAccrualPlan;
use App\Models\UserBalance;
use App\Models\UserBalanceLog;
use App\Services\PartnerAccrual\Exceptions\PartnerAccrualException;
use App\Services\PartnerAccrual\Exceptions\UserNotEligible;
use Throwable;

/**
 * Bounded due-installment runner. Never mutates balance directly —
 * always delegates to PartnerAccrualCreditService.
 */
final class PartnerAccrualRunner
{
    public function __construct(
        private readonly PartnerAccrualDueQuery $dueQuery = new PartnerAccrualDueQuery(),
        private readonly PartnerAccrualCreditService $creditService = new PartnerAccrualCreditService(),
        private readonly PartnerAccrualPlanLifecycleService $lifecycle = new PartnerAccrualPlanLifecycleService(),
        private readonly PartnerAccrualBusinessClock $clock = new PartnerAccrualBusinessClock(),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(
        bool $dryRun = true,
        ?int $limit = null,
        ?int $planId = null,
        ?string $businessDate = null,
    ): array {
        $enabled = (bool) config('partner_accrual.enabled', false);
        $asOf = $businessDate ?? $this->clock->todayDateString();
        $batch = $limit ?? (int) config('partner_accrual.max_installments_per_run', 50);
        $batch = max(1, $batch);

        $due = $this->dueQuery->findDueInstallments($asOf, $batch, $planId);
        $overdueRemaining = max(0, $this->dueQuery->countOverdue($asOf, $planId) - $due->count());

        $rows = [];
        $wouldCreditTotal = AccrualMoney::zero();
        $wouldCreditCount = 0;
        $creditedCount = 0;
        $creditedTotal = AccrualMoney::zero();
        $skipped = 0;
        $failed = 0;
        $pausedIneligible = 0;

        $balanceMutations = 0;
        $installmentMutations = 0;
        $auditMutations = 0;

        $planIds = [];

        foreach ($due as $installment) {
            /** @var PartnerAccrualInstallment $installment */
            $plan = PartnerAccrualPlan::query()->find($installment->plan_id);
            if (! $plan) {
                continue;
            }
            $planIds[$plan->id] = true;

            $eligible = $this->dueQuery->userEligible((int) $plan->user_id);
            $row = [
                'plan_id' => (int) $plan->id,
                'installment_id' => (int) $installment->id,
                'user_id' => (int) $plan->user_id,
                'scheduled_for' => $installment->scheduled_for?->toDateString() ?? (string) $installment->scheduled_for,
                'amount' => AccrualMoney::scale8(AccrualMoney::of($installment->amount)),
                'current_status' => $installment->status->value,
                'eligibility' => $eligible ? 'ELIGIBLE' : 'USER_NOT_ELIGIBLE',
                'outcome' => null,
            ];

            if ($dryRun) {
                if ($eligible) {
                    $wouldCreditCount++;
                    $wouldCreditTotal = $wouldCreditTotal->plus(AccrualMoney::of($installment->amount));
                    $row['outcome'] = 'WOULD_CREDIT';
                } else {
                    $row['outcome'] = 'WOULD_PAUSE_USER_INELIGIBLE';
                    $skipped++;
                }
                $rows[] = $row;
                continue;
            }

            // Apply path — gated by feature flags at command layer; runner still respects enabled.
            if (! $enabled) {
                $row['outcome'] = 'FEATURE_DISABLED';
                $rows[] = $row;
                $skipped++;
                continue;
            }

            if (! $eligible) {
                $this->lifecycle->pauseForUserIneligible((int) $plan->id, 'USER_NOT_ELIGIBLE');
                $row['outcome'] = 'PLAN_PAUSED_USER_INELIGIBLE';
                $pausedIneligible++;
                $installmentMutations++; // plan event / status only
                $rows[] = $row;
                continue;
            }

            $balBefore = UserBalance::query()->where('id_user', $plan->user_id)->value('balance');
            $auditBefore = UserBalanceLog::query()->where('source', PartnerAccrualCurrency::AUDIT_SOURCE)->count();
            $statusBefore = $installment->status->value;

            try {
                $result = $this->creditService->creditInstallment((int) $installment->id, dryRun: false);
                $row['outcome'] = $result->outcome;
                if ($result->outcome === CreditResult::CREDITED) {
                    $creditedCount++;
                    $creditedTotal = $creditedTotal->plus(AccrualMoney::of($result->credit ?? $installment->amount));
                    $balanceMutations++;
                    $installmentMutations++;
                    $auditMutations++;
                } elseif ($result->outcome === CreditResult::ALREADY_CREDITED) {
                    $skipped++;
                }
            } catch (UserNotEligible $e) {
                $this->lifecycle->pauseForUserIneligible((int) $plan->id, $e->getMessage());
                $row['outcome'] = 'PLAN_PAUSED_USER_INELIGIBLE';
                $pausedIneligible++;
            } catch (PartnerAccrualException $e) {
                $row['outcome'] = $e->codeName();
                $failed++;
                $installment->refresh();
                if ($installment->status->value !== $statusBefore) {
                    $installmentMutations++;
                }
            } catch (Throwable $e) {
                $row['outcome'] = 'UNEXPECTED_ERROR';
                $row['error'] = $e->getMessage();
                $failed++;
            }

            $balAfter = UserBalance::query()->where('id_user', $plan->user_id)->value('balance');
            $auditAfter = UserBalanceLog::query()->where('source', PartnerAccrualCurrency::AUDIT_SOURCE)->count();
            if ((string) $balBefore !== (string) $balAfter) {
                // already counted on CREDITED
            }
            if ($auditAfter > $auditBefore && $row['outcome'] !== CreditResult::CREDITED) {
                $auditMutations++;
            }

            $rows[] = $row;
        }

        return [
            'dry_run' => $dryRun,
            'business_date' => $asOf,
            'business_timezone' => $this->clock->timezone(),
            'feature_enabled' => $enabled,
            'due_plans' => count($planIds),
            'due_installments' => $due->count(),
            'would_credit_count' => $wouldCreditCount,
            'would_credit_total' => AccrualMoney::display2($wouldCreditTotal),
            'credited_count' => $creditedCount,
            'credited_total' => AccrualMoney::display2($creditedTotal),
            'skipped' => $skipped,
            'failed' => $failed,
            'paused_user_ineligible' => $pausedIneligible,
            'overdue_remaining' => $overdueRemaining,
            'batch_limit' => $batch,
            'balance_mutations' => $dryRun ? 0 : $balanceMutations,
            'installment_mutations' => $dryRun ? 0 : $installmentMutations,
            'audit_mutations' => $dryRun ? 0 : $auditMutations,
            'installments' => $rows,
        ];
    }
}
