<?php

declare(strict_types=1);

namespace App\Console\Schedule;

/**
 * BestChange scheduler admission and shared-flock yield policy.
 *
 * Reconciliation role (post fast-market plane): persisted catalog / fallback /
 * admin consistency — NOT the 30s customer freshness engine.
 *
 * This policy:
 *   - admits on a safer nominal offset (past courses + boundary schedule:run)
 *   - promotes every-minute catch-up ONLY as recovery after a missed nominal
 *     30m cycle (threshold > rates cadence), never as steady-state freshness
 *   - shouldDeferNonBc remains available for ops lock yield during recovery;
 *     ExchangeSchedule customer-adjacent jobs use busy-signal yield only
 *
 * Does not change rates math, book reuse, locks, or the fast-market plane.
 */
final class BestchangeAdmissionPolicy
{
    /**
     * Minutes after the rates boundary (:00/:30) when BC is nominally admitted.
     *
     * 6 (not 5): courses P95+jitter can occupy past :02; schedule:run from the
     * boundary often holds schedule-run.lock into :02–:03; competitors cron
     * 3-57/2 owns :05. Offset 6 is even, after competitors :05, before :07.
     */
    public const ADMISSION_OFFSET_MINUTES = 6;

    /**
     * Recovery-only catch-up threshold (seconds since last successful BC generation).
     * Must exceed the operator rates cadence (1800s) so catch-up is not the
     * steady-state admission path. MODEL B: 45m recovery after missed nominal.
     */
    public const OVERDUE_CATCHUP_SECONDS = 2700;

    public const LAST_SUCCESS_PATH = '/var/lock/exswaping-compiler-guards/last-success.compiler_bestchange';

    /**
     * Laravel schedule expression for compiler:bestchange.
     * Admission filtering is applied via shouldAdmit() on everyMinute ticks.
     */
    public const SCHEDULE_EXPRESSION = '* * * * *';

    public static function isNominalAdmissionMinute(
        int $minuteOfHour,
        int $ratesMinutes = 30,
        int $offsetMinutes = self::ADMISSION_OFFSET_MINUTES
    ): bool {
        $ratesMinutes = $ratesMinutes <= 0 ? 30 : $ratesMinutes;
        $offsetMinutes = max(0, min($offsetMinutes, max(0, $ratesMinutes - 1)));
        $minuteOfHour = ((int) $minuteOfHour) % 60;
        if ($minuteOfHour < 0) {
            $minuteOfHour += 60;
        }

        return ($minuteOfHour % $ratesMinutes) === $offsetMinutes;
    }

    public static function shouldAdmit(
        int $minuteOfHour,
        ?int $lastSuccessAgeSeconds,
        int $ratesMinutes = 30,
        int $offsetMinutes = self::ADMISSION_OFFSET_MINUTES,
        int $overdueSeconds = self::OVERDUE_CATCHUP_SECONDS
    ): bool {
        if ($lastSuccessAgeSeconds !== null && $lastSuccessAgeSeconds >= $overdueSeconds) {
            return true;
        }

        return self::isNominalAdmissionMinute($minuteOfHour, $ratesMinutes, $offsetMinutes);
    }

    public static function shouldDeferNonBc(
        ?int $lastSuccessAgeSeconds,
        int $overdueSeconds = self::OVERDUE_CATCHUP_SECONDS
    ): bool {
        return $lastSuccessAgeSeconds !== null && $lastSuccessAgeSeconds >= $overdueSeconds;
    }

    public static function lastSuccessEpoch(?string $path = null): ?int
    {
        $path = $path ?? (string) (getenv('EXSWAPING_BC_LAST_SUCCESS_PATH') ?: self::LAST_SUCCESS_PATH);
        if (! is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if (! is_string($raw) || $raw === '') {
            $mtime = @filemtime($path);

            return $mtime !== false ? (int) $mtime : null;
        }
        if (preg_match('/^success_epoch=(\d+)/m', $raw, $m) === 1) {
            return (int) $m[1];
        }
        $mtime = @filemtime($path);

        return $mtime !== false ? (int) $mtime : null;
    }

    public static function lastSuccessAgeSeconds(?int $nowEpoch = null, ?string $path = null): ?int
    {
        $epoch = self::lastSuccessEpoch($path);
        if ($epoch === null) {
            return null;
        }
        $nowEpoch ??= time();

        return (int) max(0, $nowEpoch - $epoch);
    }

    /**
     * Mutex-name cadence expression (must match schedule registration).
     */
    public static function mutexCronExpression(): string
    {
        return self::SCHEDULE_EXPRESSION;
    }
}
