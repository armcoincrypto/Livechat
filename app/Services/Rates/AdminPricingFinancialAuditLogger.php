<?php

declare(strict_types=1);

namespace App\Services\Rates;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Financial pricing admin audit (Position / Profit / fees / ownership / rate_mode).
 * JSONL under storage/app/rates/admin-pricing-audit. Never logs secrets.
 */
final class AdminPricingFinancialAuditLogger
{
    private const REL_DIR = 'app/rates/admin-pricing-audit';

    private const MAX_FILES = 30;

    /**
     * @param  array<string,mixed>  $payload
     */
    public static function record(array $payload): string
    {
        $changeId = (string) ($payload['change_id'] ?? Str::uuid());

        try {
            $row = [
                'ts' => gmdate('c'),
                'change_id' => $changeId,
                'request_id' => $payload['request_id'] ?? null,
                'event' => (string) ($payload['event'] ?? 'admin_pricing_change'),
                'operator' => [
                    'id' => $payload['operator_id'] ?? Auth::id(),
                    'email' => $payload['operator_email'] ?? (Auth::user()->email ?? null),
                ],
                'direction_id' => isset($payload['direction_id']) ? (int) $payload['direction_id'] : null,
                'pair' => $payload['pair'] ?? null,
                'old' => [
                    'position' => $payload['old_position'] ?? null,
                    'profit' => $payload['old_profit'] ?? null,
                    'fix_fee' => $payload['old_fix_fee'] ?? null,
                    'rate_mode' => $payload['old_rate_mode'] ?? null,
                    'owner' => $payload['old_owner'] ?? null,
                ],
                'new' => [
                    'position' => $payload['new_position'] ?? null,
                    'profit' => $payload['new_profit'] ?? null,
                    'fix_fee' => $payload['new_fix_fee'] ?? null,
                    'rate_mode' => $payload['new_rate_mode'] ?? null,
                    'owner' => $payload['new_owner'] ?? null,
                ],
                'before' => [
                    'base' => $payload['before_base'] ?? null,
                    'customer_rate' => $payload['before_customer_rate'] ?? null,
                ],
                'after' => [
                    'base' => $payload['after_base'] ?? null,
                    'customer_rate' => $payload['after_customer_rate'] ?? null,
                ],
                'writer' => (string) ($payload['writer'] ?? 'unknown'),
                'ip' => $payload['ip'] ?? null,
                'route' => $payload['route'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'affected_count' => $payload['affected_count'] ?? null,
            ];

            $dir = storage_path(self::REL_DIR);
            if (!is_dir($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            $file = $dir.'/'.gmdate('Y-m-d').'.jsonl';
            File::append($file, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
            self::prune($dir);
        } catch (\Throwable $e) {
            Log::warning('admin_pricing_financial_audit_failed', ['message' => $e->getMessage()]);
        }

        return $changeId;
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
