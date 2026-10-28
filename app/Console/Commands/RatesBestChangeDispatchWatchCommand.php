<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Schedule\ExchangeSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Read-only BestChange dispatch/heartbeat observability.
 *
 * Closes the "43 minutes of silence with no skip reason" gap by logging every
 * minute whether the BC compiler event is runnable, blocked by Laravel mutex,
 * blocked by flock, or actively running — plus book heartbeat age.
 *
 * Does not mutate rates, ownership, locks, or mutexes.
 */
final class RatesBestChangeDispatchWatchCommand extends Command
{
    /** Aligns with operator rates cadence (cron_interval_minutes_update_rates=30). */
    public const HEARTBEAT_TARGET_SECONDS = 1800;

    /** Aligns with BestchangeAdmissionPolicy::OVERDUE_CATCHUP_SECONDS (recovery). */
    public const HEARTBEAT_WARNING_SECONDS = 2700;

    public const HEARTBEAT_CRITICAL_SECONDS = 3600;

    /**
     * Laravel withoutOverlapping unit for compiler:bestchange (minutes).
     * Kept in sync with ExchangeSchedule::BESTCHANGE_WITHOUT_OVERLAPPING_MINUTES.
     */
    public const BC_MUTEX_TTL_MINUTES = ExchangeSchedule::BESTCHANGE_WITHOUT_OVERLAPPING_MINUTES;

    protected $signature = 'rates:bestchange-dispatch-watch
        {--format=json : json|text}
        {--mutex-name= : Override Laravel event mutex name}';

    protected $description = 'BestChange compiler dispatch/heartbeat watch (read-only; no rate mutation).';

    public function handle(): int
    {
        $payload = $this->snapshot();
        $line = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ((string) $this->option('format') === 'text') {
            $this->line(sprintf(
                '[%s] decision=%s heartbeat_age=%s mutex=%s flock=%s pid=%s',
                $payload['timestamp'],
                $payload['decision'],
                $payload['heartbeat_age_sec'] ?? 'null',
                $payload['laravel_mutex']['exists'] ? 'HELD' : 'FREE',
                $payload['flock']['held'] ? 'HELD' : 'FREE',
                $payload['compiler_pid'] ?? 'none'
            ));
        } else {
            $this->line($line ?: '{}');
        }

        $this->appendJsonl($payload);

        $severity = (string) ($payload['severity'] ?? 'OK');

        return $severity === 'CRITICAL' ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<string,mixed>
     */
    public function snapshot(): array
    {
        $now = now();
        $heartbeatAge = $this->heartbeatAgeSeconds();
        $mutexName = (string) ($this->option('mutex-name') ?: $this->resolveMutexName());
        $mutexExists = $mutexName !== '' ? Cache::store()->has($mutexName) || $this->redisLockExists($mutexName) : false;
        $flock = $this->flockProbe();
        $pid = $this->findCompilerPid();
        $lastSuccess = $this->lastSuccessMeta();

        $decision = 'EVENT_DUE_DISPATCHABLE';
        $skipReason = null;
        $mutexState = 'FREE';

        if ($pid !== null) {
            $decision = 'RUN_ACTIVE_EXPECTED';
            $skipReason = 'PREVIOUS_RUN_ACTIVE';
            $mutexState = $mutexExists ? 'HELD_EXPECTED' : 'FREE_WHILE_PID_LIVE';
        } elseif ($mutexExists) {
            $mutexAgeSuspect = $heartbeatAge !== null && $heartbeatAge > self::BC_MUTEX_TTL_MINUTES * 60;
            if ($mutexAgeSuspect || ($heartbeatAge !== null && $heartbeatAge > self::HEARTBEAT_WARNING_SECONDS)) {
                $decision = 'MUTEX_STALE_SUSPECTED';
                $skipReason = 'LARAVEL_MUTEX_HELD';
                $mutexState = 'HELD_STALE_SUSPECTED';
            } else {
                $decision = 'EVENT_SKIPPED';
                $skipReason = 'LARAVEL_MUTEX_HELD';
                $mutexState = 'HELD';
            }
        } elseif ($flock['held']) {
            $holder = $this->classifyFlockHolder();
            if ($holder === 'POST_BC') {
                $decision = 'POST_BC_RUNNING';
                $skipReason = 'FLOCK_HELD_POST_BC';
            } elseif ($holder === 'COURSES' || $holder === 'COMPETITORS' || $holder === 'OTHER_COMPILER') {
                $decision = 'EVENT_SKIPPED';
                $skipReason = 'FLOCK_HELD_'.$holder;
            } else {
                $decision = 'EVENT_SKIPPED';
                $skipReason = 'FLOCK_HELD';
            }
        }

        $severity = 'OK';
        if ($heartbeatAge !== null && $heartbeatAge >= self::HEARTBEAT_CRITICAL_SECONDS) {
            $severity = 'CRITICAL';
            if ($decision === 'EVENT_DUE_DISPATCHABLE') {
                $decision = 'MISSED_GENERATION_SUSPECTED';
                $skipReason = 'HEARTBEAT_CRITICAL_WHILE_DISPATCHABLE';
            }
        } elseif ($heartbeatAge !== null && $heartbeatAge >= self::HEARTBEAT_WARNING_SECONDS) {
            $severity = 'WARNING';
        } elseif ($decision === 'MUTEX_STALE_SUSPECTED') {
            $severity = 'WARNING';
        }

        return [
            'timestamp' => $now->toIso8601String(),
            'event' => 'compiler:bestchange',
            'event_due' => true,
            'decision' => $decision,
            'skip_reason' => $skipReason,
            'severity' => $severity,
            'heartbeat_age_sec' => $heartbeatAge,
            'heartbeat_slo' => [
                'target' => self::HEARTBEAT_TARGET_SECONDS,
                'warning' => self::HEARTBEAT_WARNING_SECONDS,
                'critical' => self::HEARTBEAT_CRITICAL_SECONDS,
            ],
            'laravel_mutex' => [
                'name' => $mutexName,
                'exists' => $mutexExists,
                'state' => $mutexState,
                'ttl_minutes_configured' => self::BC_MUTEX_TTL_MINUTES,
                'ttl_seconds_effective' => self::BC_MUTEX_TTL_MINUTES * 60,
                'unit_proven' => 'MINUTES',
                'cache_store' => (string) config('cache.default'),
            ],
            'flock' => $flock,
            'compiler_pid' => $pid,
            'last_success' => $lastSuccess,
            'mutates_rates' => false,
            'note' => 'Observability only. Does not force-release mutex or kill compilers.',
        ];
    }

    private function heartbeatAgeSeconds(): ?int
    {
        try {
            $max = DB::table('bestchange_directions')->where('status', 1)->max('updated_at');
            if ($max === null || $max === '') {
                return null;
            }

            return (int) max(0, now()->diffInSeconds(\Illuminate\Support\Carbon::parse((string) $max)));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{path:string,held:bool,probe:string}
     */
    private function flockProbe(): array
    {
        $path = (string) (getenv('EXS_LOCK_BESTCHANGE') ?: '/var/lock/build/bestchange-compiler.lock');
        if (! is_file($path)) {
            return ['path' => $path, 'held' => false, 'probe' => 'missing'];
        }
        $probe = @fopen($path, 'c+');
        if ($probe === false) {
            return ['path' => $path, 'held' => true, 'probe' => 'open_failed'];
        }
        $got = flock($probe, LOCK_EX | LOCK_NB);
        if ($got) {
            flock($probe, LOCK_UN);
            fclose($probe);

            return ['path' => $path, 'held' => false, 'probe' => 'free'];
        }
        fclose($probe);

        return ['path' => $path, 'held' => true, 'probe' => 'held'];
    }

    private function findCompilerPid(): ?int
    {
        $p = Process::fromShellCommandline(
            "ps -eo pid,cmd --no-headers | awk '/artisan compiler:bestchange/ && !/awk/ {print \$1; exit}'"
        );
        $p->run();
        $out = trim($p->getOutput());
        if ($out !== '' && ctype_digit($out)) {
            return (int) $out;
        }

        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function lastSuccessMeta(): ?array
    {
        $path = '/var/lock/exswaping-compiler-guards/last-success.compiler_bestchange';
        if (! is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        $meta = ['path' => $path, 'mtime' => @filemtime($path)];
        if (is_string($raw) && $raw !== '') {
            foreach (explode("\n", $raw) as $line) {
                if (str_contains($line, '=')) {
                    [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
                    $meta[trim($k)] = trim($v);
                }
            }
        }

        return $meta;
    }

    private function resolveMutexName(): string
    {
        $wrapper = '/opt/exswaping-owned-ops/current/scripts/ops/run_backend_compiler.sh';
        $command = $wrapper.' compiler:bestchange';
        // Must match ExchangeSchedule BC registration (everyMinute + when filter).
        // Nominal offset / overdue catch-up are when() gates — not mutex identity.
        $expression = \App\Console\Schedule\BestchangeAdmissionPolicy::mutexCronExpression();
        $computed = 'framework'.DIRECTORY_SEPARATOR.'schedule-'.sha1($expression.$command);

        // Prefer a live schedule:finish mutex if it matches the BC wrapper command.
        $live = $this->discoverLiveBestchangeMutexName();
        if ($live !== null) {
            return $live;
        }

        return $computed;
    }

    private function discoverLiveBestchangeMutexName(): ?string
    {
        $p = Process::fromShellCommandline(
            "ps -eo cmd --no-headers | awk '/schedule:finish \"framework\\/schedule-/ && /compiler:bestchange/ {print; exit}'"
        );
        $p->run();
        $out = trim($p->getOutput());
        if ($out === '') {
            return null;
        }
        if (preg_match('#framework/schedule-[a-f0-9]{40}#', $out, $m) === 1) {
            return $m[0];
        }

        return null;
    }

    /**
     * Best-effort flock holder classification for observability only.
     */
    private function classifyFlockHolder(): string
    {
        $p = Process::fromShellCommandline(
            "ps -eo pid,cmd --no-headers | awk '/run_backend_compiler.sh|artisan compiler:/ && !/awk/ {print}'"
        );
        $p->run();
        $out = $p->getOutput();
        if (str_contains($out, 'artisan compiler:bestchange')) {
            return 'BESTCHANGE';
        }
        // Wrapper still holds flock after artisan exits during POST_BC_XML_REFRESH.
        if (str_contains($out, 'compiler:bestchange')) {
            return 'POST_BC';
        }
        if (str_contains($out, 'compiler:courses')) {
            return 'COURSES';
        }
        if (str_contains($out, 'competitors-only')) {
            return 'COMPETITORS';
        }
        if (str_contains($out, 'run_backend_compiler.sh')) {
            return 'OTHER_COMPILER';
        }

        return 'UNKNOWN';
    }

    private function redisLockExists(string $name): bool
    {
        try {
            if (config('cache.default') !== 'redis') {
                return Cache::store()->has($name);
            }
            /** @var \Illuminate\Redis\Connections\Connection $redis */
            $redis = Cache::getRedis();
            // Redis lock keys vary by driver; probe common patterns.
            foreach ([$name, 'laravel_cache:'.$name, '{'.$name.'}'] as $key) {
                if ((int) $redis->exists($key) > 0) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return Cache::store()->has($name);
        }

        return false;
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function appendJsonl(array $payload): void
    {
        $path = storage_path('logs/bestchange_dispatch_watch.jsonl');
        $dir = dirname($path);
        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND | LOCK_EX);

        $ops = '/var/lib/exswaping-owned/pricing/authority-drift';
        if (is_dir($ops) && is_writable($ops)) {
            file_put_contents(
                $ops.'/bestchange_dispatch_watch.jsonl',
                json_encode($payload, JSON_UNESCAPED_UNICODE)."\n",
                FILE_APPEND | LOCK_EX
            );
        }
    }
}
