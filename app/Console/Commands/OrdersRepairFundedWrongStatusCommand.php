<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Orders\Reconciliation\FundedWrongStatusRepairer;
use App\Services\Orders\Reconciliation\FundedWrongStatusRepairException;
use Illuminate\Console\Command;

/**
 * Explicit allowlisted WAITING_HANDLE → PAID repair. Default is dry-run.
 */
final class OrdersRepairFundedWrongStatusCommand extends Command
{
    protected $signature = 'orders:repair-funded-wrong-status
        {task_id : Task primary key}
        {--public-id= : Must match allowlist public_id}
        {--from=3 : Current status}
        {--to=7 : Target status}
        {--execute : Persist the single-row repair}';

    protected $description = 'Allowlisted 3→7 funded-wrong-status repair (no payout, no complete).';

    public function handle(FundedWrongStatusRepairer $repairer): int
    {
        $taskId = (int) $this->argument('task_id');
        $from = (int) $this->option('from');
        $to = (int) $this->option('to');
        $publicId = (string) $this->option('public-id');

        $spec = FundedWrongStatusRepairer::PRODUCTION_ALLOWLIST[$taskId] ?? null;
        if ($spec === null || $from !== (int) $spec['from_status'] || $to !== (int) $spec['to_status']) {
            $this->error('REFUSED: not an exact allowlisted 3→7 repair.');

            return self::FAILURE;
        }
        if ($publicId !== '' && $publicId !== (string) $spec['public_id']) {
            $this->error('REFUSED: public_id does not match allowlist.');

            return self::FAILURE;
        }

        if (!$this->option('execute')) {
            $this->warn('DRY-RUN: pass --execute to persist. task_id='.$taskId.' '.$spec['from_status'].'→'.$spec['to_status']);

            return self::SUCCESS;
        }

        try {
            $result = $repairer->repair($taskId);
        } catch (FundedWrongStatusRepairException $e) {
            $this->error($e->errorCode.': '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
