<?php
declare(strict_types=1);

namespace iEXPackages\OnlinePresence\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OnlineSimulateCommand extends Command
{
    protected $signature = 'online:simulate
        {--guests=0 : How many guest sessions to simulate}
        {--users=0  : How many auth user sessions to simulate (picked from users table)}
        {--window= : Online window seconds (default config online_presence.window_seconds)}
        {--wipe=0  : If 1, wipe current online_sessions for selected types before insert}
    ';

    protected $description = 'Simulate online guests and authenticated users (for testing UI/admin lists)';

    public function handle(): int
    {
        $guests = max(0, (int) $this->option('guests'));
        $users  = max(0, (int) $this->option('users'));
        $wipe   = (int) $this->option('wipe') === 1;

        $window = $this->option('window') !== null
            ? max(30, (int) $this->option('window'))
            : max(30, (int) config('online_presence.window_seconds', 300));

        $now = CarbonImmutable::now();

        if ($wipe) {
            $q = DB::table('online_sessions');
            if ($guests > 0 && $users > 0) {
                $q->whereIn('type', ['guest', 'user']);
            } elseif ($guests > 0) {
                $q->where('type', 'guest');
            } elseif ($users > 0) {
                $q->where('type', 'user');
            } else {
                // если wipe=1, но ничего не выбрано — не трогаем
                $q = null;
            }

            if ($q) {
                $deleted = (int) $q->delete();
                $this->info("Wiped online_sessions rows: {$deleted}");
            }
        }

        $insertedGuests = 0;
        $insertedUsers  = 0;

        // -------------------------
        // Guests
        // -------------------------
        if ($guests > 0) {
            for ($i = 0; $i < $guests; $i++) {
                $gid = (string) Str::ulid();
                $identity = "guest:{$gid}";

                // last_seen внутри окна (рандомно)
                $lastSeen = $now->subSeconds(random_int(0, $window - 1));
                $firstSeen = $lastSeen->subSeconds(random_int(0, min(60, $window - 1)));

                DB::table('online_sessions')->updateOrInsert(
                    ['identity' => $identity],
                    [
                        'type' => 'guest',
                        'user_id' => null,
                        'gid' => $gid,
                        'user_name' => null,
                        'user_email' => null,
                        'first_seen' => $firstSeen,
                        'last_seen' => $lastSeen,
                        'hits' => random_int(1, 20),
                        'ip' => null,
                        'ua_hash' => null, // лучше null, чтобы не мешать json/диагностике
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );

                $insertedGuests++;
            }
        }

        // -------------------------
        // Users (from users table)
        // -------------------------
        if ($users > 0) {
            // Берём случайных пользователей
            $userRows = DB::table('users')
                ->select(['id', 'name', 'email'])
                ->inRandomOrder()
                ->limit($users)
                ->get();

            foreach ($userRows as $u) {
                $userId = (int) $u->id;
                if ($userId <= 0) {
                    continue;
                }

                $identity = "user:{$userId}";

                $lastSeen = $now->subSeconds(random_int(0, $window - 1));
                $firstSeen = $lastSeen->subSeconds(random_int(0, min(120, $window - 1)));

                DB::table('online_sessions')->updateOrInsert(
                    ['identity' => $identity],
                    [
                        'type' => 'user',
                        'user_id' => $userId,
                        'gid' => null,
                        'user_name' => is_string($u->name) ? $u->name : null,
                        'user_email' => is_string($u->email) ? $u->email : null,
                        'first_seen' => $firstSeen,
                        'last_seen' => $lastSeen,
                        'hits' => random_int(1, 50),
                        'ip' => null,
                        'ua_hash' => null,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );

                $insertedUsers++;
            }
        }

        $this->info("Simulated: guests={$insertedGuests}, users={$insertedUsers}, window={$window}s");

        return self::SUCCESS;
    }
}
