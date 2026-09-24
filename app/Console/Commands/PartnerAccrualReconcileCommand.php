<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PartnerAccrualStatus;
use App\Models\PartnerAccrualPlan;
use App\Services\PartnerAccrual\PartnerAccrualObservability;
use App\Services\PartnerAccrual\PartnerAccrualReconciliationService;
use Illuminate\Console\Command;

/** Global read-only reconciliation / health. No mutation. */
final class PartnerAccrualReconcileCommand extends Command
{
    protected $signature = 'partner-accrual:reconcile
        {--dry-run : Required; read-only inconsistent plan listing}
        {--health : Print observability snapshot}';

    protected $description = 'Read-only partner accrual reconcile / health (no auto-repair).';

    public function handle(
        PartnerAccrualReconciliationService $reconcile,
        PartnerAccrualObservability $obs,
    ): int {
        if (! $this->option('dry-run') && ! $this->option('health')) {
            $this->error('Specify --dry-run and/or --health');

            return self::FAILURE;
        }

        if ($this->option('health')) {
            foreach ($obs->snapshot() as $k => $v) {
                $this->line($k.'='.(is_bool($v) ? ($v ? 'true' : 'false') : (string) ($v ?? 'null')));
            }
        }

        if ($this->option('dry-run')) {
            $plans = PartnerAccrualPlan::query()
                ->whereIn('status', [
                    PartnerAccrualStatus::ACTIVE->value,
                    PartnerAccrualStatus::PAUSED->value,
                    PartnerAccrualStatus::COMPLETED->value,
                    PartnerAccrualStatus::ERROR->value,
                ])
                ->orderBy('id')
                ->limit(500)
                ->get();

            $mismatches = 0;
            foreach ($plans as $plan) {
                $r = $reconcile->reconcilePlan((int) $plan->id);
                if ($r['status'] !== PartnerAccrualReconciliationService::CONSISTENT) {
                    $mismatches++;
                    $this->line(sprintf(
                        'plan_id=%d status=%s reconciliation=%s details=%s',
                        $plan->id,
                        $plan->status->value,
                        $r['status'],
                        implode(';', $r['details'])
                    ));
                }
            }
            $this->line('INCONSISTENT_PLANS='.$mismatches);
            $this->line('AUTO_REPAIR=NO');
        }

        return self::SUCCESS;
    }
}
