<?php

declare(strict_types=1);

namespace App\Console\Commands;

use iEXPackages\ReferralSystem\ReferralEngine;
use Illuminate\Console\Command;

class ReferralSettleHoldsCommand extends Command
{
    protected $signature = 'referral:settle-holds {--limit=500}';
    protected $description = 'Confirm due referral holds (pending -> confirmed)';

    public function handle(ReferralEngine $engine): int
    {
        $limit = max(1, min(5000, (int) $this->option('limit')));
        $count = $engine->settleDueHolds($limit);

        $this->info("Settled: {$count}");
        return self::SUCCESS;
    }
}
