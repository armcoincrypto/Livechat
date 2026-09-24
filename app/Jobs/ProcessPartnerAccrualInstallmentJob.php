<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\PartnerAccrual\PartnerAccrualCreditService;
use App\Services\PartnerAccrual\PartnerAccrualPlanLifecycleService;
use App\Services\PartnerAccrual\Exceptions\UserNotEligible;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Queue payload carries installment_id only — amount is always re-read from DB.
 */
class ProcessPartnerAccrualInstallmentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $installmentId,
    ) {}

    public function handle(
        PartnerAccrualCreditService $creditService,
        PartnerAccrualPlanLifecycleService $lifecycle,
    ): void {
        if (! config('partner_accrual.enabled')) {
            return;
        }

        try {
            $creditService->creditInstallment($this->installmentId, dryRun: false);
        } catch (UserNotEligible $e) {
            $installment = \App\Models\PartnerAccrualInstallment::query()->find($this->installmentId);
            if ($installment) {
                $lifecycle->pauseForUserIneligible((int) $installment->plan_id, $e->getMessage());
            }
        } catch (Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
