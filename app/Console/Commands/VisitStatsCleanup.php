<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VisitStatsCleanup extends Command
{
    protected $signature = 'visits:cleanup {hours_keep=17520} {days_keep=1825}';
    protected $description = 'Delete old rows from visit_counters_hour and user_request_daily';

    public function handle(): int
    {
        $hours = (int) $this->argument('hours_keep'); // ~2 года
        $days  = (int) $this->argument('days_keep');  // ~5 лет

        $hCut = now('UTC')->subHours($hours);
        $dCut = now('UTC')->subDays($days)->toDateString(); // YYYY-MM-DD

        DB::table('visit_counters_hour')->where('bucket_hour', '<', $hCut)->delete();
        DB::table('user_request_daily')->where('bucket_day', '<', $dCut)->delete();

        $this->info('Cleanup done.');
        return self::SUCCESS;
    }
}
