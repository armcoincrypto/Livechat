<?php

declare(strict_types=1);

namespace App\Console\Schedule;

/**
 * BestChange scheduler admission and shared-flock yield policy.
 *
 * Wave1 (+2m offset) left BC inside the courses start-jitter + P95 hold envelope
 * and did not catch up when an admission minute was skipped (schedule-run.lock or
 * flock). This policy:
 *   - admits on a safer nominal offset (past courses + boundary schedule:run)
 *   - promotes every-minute catch-up once generation age exceeds the freshness
 *     target, without raising the 1800s financial policy or bypassing locks
 *   - tells non-BC shared-flock compilers to yield while BC is overdue
 *
 * Does not change rates math, book reuse, or the freshness order gate.
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
     * Catch-up threshold (seconds since last successful BC generation).
     * Aligns with dispatch-watch HEARTBEAT_TARGET — headroom under the 1800s
     * financial freshness policy, not a policy extension.
     */
    public const OVERDUE_CATCHUP_SECONDS = 900;

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
