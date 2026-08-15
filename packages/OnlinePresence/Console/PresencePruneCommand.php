<?php
declare(strict_types=1);

namespace iEXPackages\OnlinePresence\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PresencePruneCommand extends Command
{
    protected $signature = 'presence:prune';
    protected $description = 'Delete old online_sessions (guests/users) keeping analytics';

    public function handle(): int
    {
        $now = CarbonImmutable::now();

        $guestDays = max(1, (int) config('online_presence.retention_days_guests', 3));
        $userDays  = max(1, (int) config('online_presence.retention_days_users', 14));

        $guestBefore = $now->subDays($guestDays);
        $userBefore  = $now->subDays($userDays);

        $deletedGuests = (int) DB::table('online_sessions')
            ->where('type', 'guest')
            ->where('last_seen', '<', $guestBefore)
            ->delete();

        $deletedUsers = (int) DB::table('online_sessions')
            ->where('type', 'user')
            ->where('last_seen', '<', $userBefore)
            ->delete();

        $this->info("OK deleted_guests={$deletedGuests} deleted_users={$deletedUsers}");
        return self::SUCCESS;
    }
}
