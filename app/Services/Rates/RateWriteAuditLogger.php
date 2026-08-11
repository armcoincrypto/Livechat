<?php

declare(strict_types=1);

namespace App\Services\Rates;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Bounded JSONL audit trail for material automatic BASE changes / blocks.
 *
 * Retention: keep the last {@see MAX_FILES} daily files under storage/app/rates/write-audit.
 */
final class RateWriteAuditLogger
{
    private const REL_DIR = 'app/rates/write-audit';

    private const MAX_FILES = 14;

    /**
     * @param  array{
     *   event?:string,
     *   direction_id:?int,
     *   from?:?string,
     *   to?:?string,
     *   old_base?:?string,
     *   new_base?:?string,
     *   writer:string,
     *   source?:?string,
     *   reason?:?string,
     *   job_run_id?:?string
     * }  $payload
     */
    public static function record(array $payload): void
    {
        try {
            $old = isset($payload['old_base']) && is_numeric((string) $payload['old_base'])
                ? (string) $payload['old_base']
                : null;
            $new = isset($payload['new_base']) && is_numeric((string) $payload['new_base'])
                ? (string) $payload['new_base']
                : null;

            $deltaPct = null;
            if ($old !== null && $new !== null && (float) $old > 0) {
                $deltaPct = (((float) $new - (float) $old) / (float) $old) * 100.0;
            }

            $row = [
                'ts' => gmdate('c'),
                'event' => (string) ($payload['event'] ?? 'base_change'),
                'direction_id' => isset($payload['direction_id']) ? (int) $payload['direction_id'] : null,
                'from' => $payload['from'] ?? null,
                'to' => $payload['to'] ?? null,
                'old_base' => $old,
                'new_base' => $new,
                'delta_pct' => $deltaPct,
                'writer' => (string) ($payload['writer'] ?? 'unknown'),
                'source' => $payload['source'] ?? null,
                'reason' => $payload['reason'] ?? null,
                'job_run_id' => $payload['job_run_id'] ?? (getenv('EXSWAPING_RATE_JOB_RUN_ID') ?: null),
            ];

            $dir = storage_path(self::REL_DIR);
            if (!is_dir($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            $file = $dir.'/'.gmdate('Y-m-d').'.jsonl';
            File::append($file, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

            self::prune($dir);
        } catch (\Throwable $e) {
            Log::warning('rate_write_audit_failed', ['message' => $e->getMessage()]);
        }
    }

    private static function prune(string $dir): void
    {
        $files = glob($dir.'/*.jsonl') ?: [];
        rsort($files, SORT_STRING);
        foreach (array_slice($files, self::MAX_FILES) as $stale) {
            @unlink($stale);
        }
    }
}
