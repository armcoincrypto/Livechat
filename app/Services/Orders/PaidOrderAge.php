<?php

declare(strict_types=1);

namespace App\Services\Orders;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Human-readable age for PAID operator queue (no alerting framework).
 */
final class PaidOrderAge
{
    public const BUCKET_ALL = 'paid';

    public const BUCKET_1H = 'gt1h';

    public const BUCKET_6H = 'gt6h';

    public const BUCKET_24H = 'gt24h';

    /** @return list<string> */
    public static function buckets(): array
    {
        return [self::BUCKET_ALL, self::BUCKET_1H, self::BUCKET_6H, self::BUCKET_24H];
    }

    public static function isValidBucket(?string $bucket): bool
    {
        return is_string($bucket) && in_array($bucket, self::buckets(), true);
    }

    /**
     * Anchor: started_at when set (payment confirmed), else updated_at.
     */
    public static function paidAt(object $task): ?CarbonInterface
    {
        $started = $task->started_at ?? null;
        if ($started) {
            return Carbon::parse($started);
        }
        $updated = $task->updated_at ?? null;

        return $updated ? Carbon::parse($updated) : null;
    }

    public static function human(object $task, ?CarbonInterface $now = null): ?string
    {
        $at = self::paidAt($task);
        if ($at === null) {
            return null;
        }
        $now ??= Carbon::now();
        $seconds = max(0, $now->getTimestamp() - $at->getTimestamp());
        if ($seconds < 60) {
            return 'Paid just now';
        }
        if ($seconds < 3600) {
            $m = (int) floor($seconds / 60);

            return 'Paid '.$m.' min ago';
        }
        if ($seconds < 86400) {
            $h = (int) floor($seconds / 3600);
            $m = (int) floor(($seconds % 3600) / 60);

            return $m > 0 ? sprintf('Paid %dh %dm ago', $h, $m) : sprintf('Paid %dh ago', $h);
        }
        $d = (int) floor($seconds / 86400);

        return $d === 1 ? 'Paid 1 day ago' : sprintf('Paid %d days ago', $d);
    }

    public static function agingLevel(object $task, ?CarbonInterface $now = null): string
    {
        $at = self::paidAt($task);
        if ($at === null) {
            return 'unknown';
        }
        $now ??= Carbon::now();
        $hours = ($now->getTimestamp() - $at->getTimestamp()) / 3600;
        if ($hours >= 24) {
            return 'critical';
        }
        if ($hours >= 6) {
            return 'warn';
        }
        if ($hours >= 1) {
            return 'attention';
        }

        return 'fresh';
    }

    /**
     * SQL age threshold in hours for bucket filters (null = all PAID).
     */
    public static function bucketMinHours(string $bucket): ?int
    {
        return match ($bucket) {
            self::BUCKET_1H => 1,
            self::BUCKET_6H => 6,
            self::BUCKET_24H => 24,
            default => null,
        };
    }
}
