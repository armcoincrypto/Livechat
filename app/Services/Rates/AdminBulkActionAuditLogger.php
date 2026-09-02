<?php

declare(strict_types=1);

namespace App\Services\Rates;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Durable JSONL audit for destructive admin bulk pricing-control mutations.
 *
 * Retention: last {@see MAX_FILES} daily files under storage/app/rates/admin-bulk-audit.
 * Large ID lists are stored as count + sha256 hash + optional artifact file.
 */
final class AdminBulkActionAuditLogger
{
    private const REL_DIR = 'app/rates/admin-bulk-audit';

    private const MAX_FILES = 30;

    /**
     * @param  array<string,mixed>  $payload
     */
    public static function record(array $payload): void
    {
        try {
            $ids = [];
            if (isset($payload['ids']) && is_array($payload['ids'])) {
                $ids = array_values(array_unique(array_map('intval', $payload['ids'])));
            }

            $count = (int) ($payload['row_count'] ?? count($ids));
            $idsHash = $ids !== [] ? hash('sha256', implode(',', $ids)) : null;

            $artifactRel = null;
            if (count($ids) > 50) {
                $dir = storage_path(self::REL_DIR.'/artifacts');
                if (!is_dir($dir)) {
                    File::makeDirectory($dir, 0755, true);
                }
                $artifactName = gmdate('Ymd\THis\Z').'-'.substr((string) $idsHash, 0, 12).'.json';
                $artifactPath = $dir.'/'.$artifactName;
                File::put($artifactPath, json_encode([
                    'ts' => gmdate('c'),
                    'ids' => $ids,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $artifactRel = 'artifacts/'.$artifactName;
            }

            $row = [
                'ts' => gmdate('c'),
                'event' => (string) ($payload['event'] ?? 'bestchange_status_bulk'),
                'action' => (string) ($payload['action'] ?? 'unknown'),
                'result' => (string) ($payload['result'] ?? 'unknown'),
                'admin_user_id' => isset($payload['admin_user_id']) ? (int) $payload['admin_user_id'] : null,
                'admin_email' => $payload['admin_email'] ?? null,
                'ip' => $payload['ip'] ?? null,
                'session_id' => $payload['session_id'] ?? null,
                'request_id' => $payload['request_id'] ?? null,
                'route' => $payload['route'] ?? null,
                'row_count' => $count,
                'ids_hash' => $idsHash,
                'ids_sample' => array_slice($ids, 0, 20),
                'ids_artifact' => $artifactRel,
                'before_status' => $payload['before_status'] ?? null,
                'after_status' => $payload['after_status'] ?? null,
                'expected_count' => $payload['expected_count'] ?? null,
                'active_bc_before' => $payload['active_bc_before'] ?? null,
                'code' => $payload['code'] ?? null,
                'message' => $payload['message'] ?? null,
                'release_sha' => $payload['release_sha'] ?? self::releaseSha(),
                'user_agent' => isset($payload['user_agent'])
                    ? substr((string) $payload['user_agent'], 0, 240)
                    : null,
            ];

            $dir = storage_path(self::REL_DIR);
            if (!is_dir($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            $file = $dir.'/'.gmdate('Y-m-d').'.jsonl';
            File::append($file, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
            self::prune($dir);
        } catch (\Throwable $e) {
            Log::warning('admin_bulk_action_audit_failed', ['message' => $e->getMessage()]);
        }
    }

    private static function releaseSha(): ?string
    {
        $live = @readlink('/var/www/app_exswapin_usr/data/www/app.exswaping.com');
        if (!is_string($live) || $live === '') {
            return null;
        }
        if (preg_match('/-([0-9a-f]{7,40})$/', basename($live), $m)) {
            return $m[1];
        }

        return basename($live);
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
