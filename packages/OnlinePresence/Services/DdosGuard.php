<?php
declare(strict_types=1);

namespace iEXPackages\OnlinePresence\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

final class DdosGuard
{
    public function shouldSilenceNow(): bool
    {
        return Cache::has($this->globalSilentKey());
    }

    public function allow(Request $request): bool
    {
        if ($this->shouldSilenceNow()) {
            return false;
        }

        $ip = (string) ($request->ip() ?? 'unknown');
        if ($ip === '') {
            $ip = 'unknown';
        }

        $ipLimit = max(1, (int) config('online_presence.ip_limit_per_minute', 60));
        $globalLimit = max(100, (int) config('online_presence.global_limit_per_minute', 50000));
        $silentMinutes = max(1, (int) config('online_presence.silent_minutes', 2));

        // 1) IP limiter (atomic on Redis)
        $ipKey = "presence:rl:ip:{$ip}";
        if (RateLimiter::tooManyAttempts($ipKey, $ipLimit)) {
            return false;
        }
        RateLimiter::hit($ipKey, 60);

        /**
         * 2) Global limiter
         *
         * Global key is a hot-spot if we hit it for every request.
         * So we sample (e.g. 1/20) which is enough to detect abnormal bursts,
         * but dramatically reduces Redis contention.
         */
        $sampleN = max(1, (int) config('online_presence.global_sample_n', 20));

        if ($sampleN === 1 || random_int(1, $sampleN) === 1) {
            $globalKey = 'presence:rl:global';

            if (RateLimiter::tooManyAttempts($globalKey, $globalLimit)) {
                Cache::put($this->globalSilentKey(), 1, $silentMinutes * 60);
                return false;
            }

            RateLimiter::hit($globalKey, 60);
        }

        return true;
    }

    private function globalSilentKey(): string
    {
        return 'presence:silent';
    }
}
