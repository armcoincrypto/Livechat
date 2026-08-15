<?php
declare(strict_types=1);

namespace iEXPackages\OnlinePresence\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

final class PresencePingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly array $payload,
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
    ) {}

    public function handle(): void
    {
        $identity = trim((string) ($this->payload['identity'] ?? ''));
        if ($identity === '') {
            return;
        }

        // -----------------------------
        // HARD NORMALIZATION (fix bug)
        // type/user_id/gid are derived from identity only.
        // This prevents "auth written as guest" and vice versa.
        // -----------------------------
        $type = '';
        $userId = null;
        $gid = null;

        if (str_starts_with($identity, 'user:')) {
            $type = 'user';
            $userId = (int) substr($identity, 5);
            if ($userId <= 0) {
                return;
            }
        } elseif (str_starts_with($identity, 'guest:')) {
            $type = 'guest';
            $gid = trim((string) substr($identity, 6));
            if ($gid === '') {
                return;
            }
        } else {
            return;
        }

        $now = CarbonImmutable::now();
        $dbThrottle = max(1, (int) config('online_presence.db_throttle_seconds', 20));
        $fromThrottle = $now->subSeconds($dbThrottle);

        $uaHash = $this->uaHash($this->userAgent);
        $storeIp = (bool) config('online_presence.store_ip', false);
        $ip = $storeIp ? ($this->ip ?: null) : null;

        // Only keep name/email for user-type
        $userName = $type === 'user' ? ($this->payload['user_name'] ?? null) : null;
        $userEmail = $type === 'user' ? ($this->payload['user_email'] ?? null) : null;

        // 1) throttled UPDATE (cheap)
        $updated = DB::table('online_sessions')
            ->where('identity', $identity)
            ->where('last_seen', '<', $fromThrottle)
            ->update([
                'type' => $type,
                'user_id' => $userId,
                'gid' => $gid,
                'user_name' => $userName,
                'user_email' => $userEmail,
                'last_seen' => $now,
                'hits' => DB::raw('hits + 1'),
                'ip' => $ip,
                'ua_hash' => $uaHash,
                'updated_at' => $now,
            ]);

        if ($updated === 0) {
            // 2) If row doesn't exist, INSERT once; if exists but throttle didn't pass — do nothing.
            $exists = DB::table('online_sessions')->where('identity', $identity)->exists();
            if (!$exists) {
                try {
                    DB::table('online_sessions')->insert([
                        'type' => $type,
                        'identity' => $identity,
                        'user_id' => $userId,
                        'gid' => $gid,
                        'user_name' => $userName,
                        'user_email' => $userEmail,
                        'first_seen' => $now,
                        'last_seen' => $now,
                        'hits' => 1,
                        'ip' => $ip,
                        'ua_hash' => $uaHash,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } catch (QueryException) {
                    // Race condition: another worker inserted concurrently.
                }
            }
        }

        // 3) Hourly hits (concurrent is handled by snapshot command)
        $hour = $now->startOfHour()->format('Y-m-d H:i:s');

        // Ensure row exists (avoids updateOrInsert with raw-increments under concurrency)
        DB::table('online_hourly_stats')->insertOrIgnore([
            'hour' => $hour,
            'auth_hits' => 0,
            'guest_hits' => 0,
            'concurrent_last' => 0,
            'concurrent_max' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('online_hourly_stats')
            ->where('hour', $hour)
            ->update([
                'auth_hits' => DB::raw('auth_hits + ' . ($type === 'user' ? 1 : 0)),
                'guest_hits' => DB::raw('guest_hits + ' . ($type === 'guest' ? 1 : 0)),
                'updated_at' => $now,
            ]);
    }

    private function uaHash(?string $ua): ?string
    {
        $ua = $ua ? trim($ua) : '';
        if ($ua === '') {
            return null;
        }

        /**
         * Default: 16 bytes binary hash (md5(..., true)).
         * Ensure your DB column is BINARY(16) / VARBINARY(16).
         */
        return md5($ua, true);
    }
}
