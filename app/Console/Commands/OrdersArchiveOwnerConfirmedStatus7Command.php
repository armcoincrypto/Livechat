<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Orders\Archival\OwnerConfirmedStatus7Archiver;
use Illuminate\Console\Command;

/**
 * Owner-attested archival of historical status=7 backlog.
 * Never mutates balances, payouts, refunds, or order status.
 */
final class OrdersArchiveOwnerConfirmedStatus7Command extends Command
{
    protected $signature = 'orders:archive-owner-confirmed-status7
        {--dry-run : Preview only (default unless --apply)}
        {--apply : Apply archival metadata (requires --i-confirm-owner-attestation)}
        {--i-confirm-owner-attestation : Required with --apply}
        {--resolved-by=owner_attestation_cli : Operator identity recorded in metadata}
        {--manifest= : Path to write dry-run / apply manifest JSON}
        {--allow-count-mismatch : Bypass expected TARGET_COUNT=340 guard (emergency only)}';

    protected $description = 'Archive status=7 rows under owner historical-settlement attestation (no money moves).';

    public function handle(OwnerConfirmedStatus7Archiver $archiver): int
    {
        $apply = (bool) $this->option('apply');
        if ($apply && !$this->option('i-confirm-owner-attestation')) {
            $this->error('REFUSED: --apply requires --i-confirm-owner-attestation');

            return self::FAILURE;
        }

        $manifest = (string) ($this->option('manifest') ?: '');
        if ($manifest === '') {
            $manifest = storage_path(
                'logs/status7-owner-archive-manifest-'.gmdate('Ymd\THis').'Z.json'
            );
        }

        try {
            $allowMismatch = (bool) $this->option('allow-count-mismatch');
            if ($allowMismatch) {
                $this->warn('allow-count-mismatch enabled');
            }

            $result = $archiver->run(
                apply: $apply,
                resolvedBy: (string) $this->option('resolved-by'),
                manifestWritePath: $manifest,
                allowCountMismatch: $allowMismatch,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($apply === false && (int) $result['unexpected_rows'] !== 0) {
            $this->warn(sprintf(
                'COUNT_MISMATCH target=%d expected=%d unexpected_delta=%d (dry-run still wrote manifest)',
                $result['target_count'],
                OwnerConfirmedStatus7Archiver::EXPECTED_TARGET,
                $result['unexpected_rows']
            ));
        }

        $summary = [
            'dry_run' => $result['dry_run'],
            'TARGET_COUNT' => $result['target_count'],
            'NEWLY_ARCHIVED' => $result['newly_archived'],
            'ALREADY_ARCHIVED' => $result['already_archived'],
            'UNEXPECTED_ROWS' => $result['unexpected_rows'],
            'ORDER_STATUS_CHANGED' => $result['status_changed'],
            'ORDERS_DELETED' => $result['orders_deleted'],
            'MANIFEST' => $manifest,
            'MANIFEST_SHA256' => $result['manifest_sha256'],
        ];
        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
