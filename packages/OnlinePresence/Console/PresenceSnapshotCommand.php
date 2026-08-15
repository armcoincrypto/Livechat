<?php
declare(strict_types=1);

namespace iEXPackages\OnlinePresence\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PresenceSnapshotCommand extends Command
{
    protected $signature = 'presence:snapshot';
    protected $description = 'Snapshot current concurrent online to hourly stats (1 COUNT per minute)';

    public function handle(): int
    {
        $window = max(30, (int) config('online_presence.window_seconds', 300));
        $now = CarbonImmutable::now();
        $from = $now->subSeconds($window);

        $concurrent = (int) DB::table('online_sessions')
            ->where('last_seen', '>=', $from)
            ->count();

        $hour = $now->startOfHour()->format('Y-m-d H:i:s');

        // concurrent_max = max(existing, concurrent)
        DB::table('online_hourly_stats')->updateOrInsert(
            ['hour' => $hour],
            [
                'concurrent_last' => $concurrent,
                'concurrent_max' => DB::raw('GREATEST(concurrent_max, ' . $concurrent . ')'),
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $this->info("OK concurrent={$concurrent} hour={$hour}");
        return self::SUCCESS;
    }
}
