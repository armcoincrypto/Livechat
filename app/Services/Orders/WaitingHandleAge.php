<?php

declare(strict_types=1);

namespace App\Services\Orders;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Age since customer marked paid (status 3 / waiting_handle).
 * Display-only — does not mutate financial status.
 */
final class WaitingHandleAge
{
    /** Unclaimed SLA signal threshold (hours). */
    public const UNCLAIMED_WARN_HOURS = 1;

    public const UNCLAIMED_CRITICAL_HOURS = 24;

    /**
     * Anchor: started_at when set (customer marked paid), else updated_at.
     */
    public static function markedPaidAt(object $task): ?CarbonInterface
    {
        $started = $task->started_at ?? null;
        if ($started) {
            return Carbon::parse($started);
        }
        $updated = $task->updated_at ?? null;

        return $updated ? Carbon::parse($updated) : null;
    }

    public static function seconds(object $task, ?CarbonInterface $now = null): ?int
    {
        $at = self::markedPaidAt($task);
        if ($at === null) {
            return null;
        }
        $now ??= Carbon::now();

        return max(0, $now->getTimestamp() - $at->getTimestamp());
    }

    public static function human(object $task, ?CarbonInterface $now = null): ?string
    {
        $seconds = self::seconds($task, $now);
        if ($seconds === null) {
            return null;
        }
        if ($seconds < 60) {
            return 'Marked paid just now';
        }
        if ($seconds < 3600) {
            $m = (int) floor($seconds / 60);

            return 'Marked paid '.$m.' min ago';
        }
        if ($seconds < 86400) {
            $h = (int) floor($seconds / 3600);
            $m = (int) floor(($seconds % 3600) / 60);

            return $m > 0
                ? sprintf('Marked paid %dh %dm ago', $h, $m)
                : sprintf('Marked paid %dh ago', $h);
        }
        $d = (int) floor($seconds / 86400);

        return $d === 1 ? 'Marked paid 1 day ago' : sprintf('Marked paid %d days ago', $d);
    }

    public static function agingLevel(object $task, ?CarbonInterface $now = null): string
    {
        $seconds = self::seconds($task, $now);
        if ($seconds === null) {
            return 'unknown';
        }
        $hours = $seconds / 3600;
        if ($hours >= self::UNCLAIMED_CRITICAL_HOURS) {
            return 'critical';
        }
        if ($hours >= 6) {
            return 'warn';
        }
        if ($hours >= self::UNCLAIMED_WARN_HOURS) {
            return 'attention';
        }

        return 'fresh';
    }

    public static function isClaimed(object $task): bool
    {
        if (isset($task->task_operators) && count($task->task_operators) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Operator-facing prompt when unclaimed beyond SLA — never auto-claims.
     */
    public static function claimPrompt(object $task, ?CarbonInterface $now = null): ?string
    {
        if (self::isClaimed($task)) {
            return null;
        }
        $seconds = self::seconds($task, $now);
        if ($seconds === null) {
            return null;
        }
        $hours = $seconds / 3600;
        if ($hours >= self::UNCLAIMED_CRITICAL_HOURS) {
            return 'UNCLAIMED >24h — claim and review funds signal';
        }
        if ($hours >= self::UNCLAIMED_WARN_HOURS) {
            return 'UNCLAIMED — claim promptly';
        }

        return null;
    }

    /**
     * Safe payment detection indicator from existing fields only.
     *
     * @return 'in_flow_funds'|'register_tx'|'no_funds_signal'
     */
    public static function paymentSignal(object $task): string
    {
        $inFlow = (int) ($task->in_flow_funds ?? 0);
        if ($inFlow === 1) {
            return 'in_flow_funds';
        }
        $register = $task->register_tx ?? null;
        if (is_string($register) && trim($register) !== '' && trim($register) !== '0') {
            return 'register_tx';
        }
        if (is_numeric($register) && (int) $register === 1) {
            return 'register_tx';
        }

        return 'no_funds_signal';
    }
}
