<?php
declare(strict_types=1);

namespace iEXPackages\OnlinePresence\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PresenceRollupDailyCommand extends Command
{
    protected $signature = 'presence:rollup-daily {day? : YYYY-mm-dd (default yesterday)}';
    protected $description = 'Build daily stats from sessions+hourly';

    public function handle(): int
    {
        $day = (string) ($this->argument('day') ?: CarbonImmutable::yesterday()->format('Y-m-d'));

        $start = CarbonImmutable::parse($day)->startOfDay();
        $end = CarbonImmutable::parse($day)->endOfDay();

        $authUnique = (int) DB::table('online_sessions')
            ->where('type', 'user')
            ->whereBetween('last_seen', [$start, $end])
            ->distinct('user_id')
            ->count('user_id');

        $guestUnique = (int) DB::table('online_sessions')
            ->where('type', 'guest')
            ->whereBetween('last_seen', [$start, $end])
            ->distinct('gid')
            ->count('gid');

        $hourRow = DB::table('online_hourly_stats')
            ->selectRaw('COALESCE(SUM(auth_hits),0) as auth_hits, COALESCE(SUM(guest_hits),0) as guest_hits, COALESCE(MAX(concurrent_max),0) as peak')
            ->whereBetween('hour', [$start, $end])
            ->first();

        $authHits = (int) ($hourRow->auth_hits ?? 0);
        $guestHits = (int) ($hourRow->guest_hits ?? 0);
        $peak = (int) ($hourRow->peak ?? 0);

        DB::table('online_daily_stats')->updateOrInsert(
            ['day' => $start->format('Y-m-d')],
            [
                'auth_unique' => $authUnique,
                'guest_unique' => $guestUnique,
                'auth_hits' => $authHits,
                'guest_hits' => $guestHits,
                'peak_concurrent' => $peak,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $this->info("OK day={$day} auth_unique={$authUnique} guest_unique={$guestUnique} peak={$peak}");
        return self::SUCCESS;
    }
}
