<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Orders\Reconciliation\FundedOrderHealthBuilder;
use App\Services\Orders\Reconciliation\FundedOrderReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Read-only funded-order reconciliation. Refuses mutation.
 */
final class OrdersReconcileFundedCommand extends Command
{
    protected $signature = 'orders:reconcile-funded
        {--status=3,7,4,1 : Comma-separated task statuses}
        {--write= : Directory to write CSV/JSON artifacts}
        {--json : Print summary JSON only}
        {--apply : Forbidden in Wave 2 (always refused)}';

    protected $description = 'Read-only funded-order reconciliation (no status writes).';

    public function handle(FundedOrderReconciler $reconciler, FundedOrderHealthBuilder $health): int
    {
        if ($this->option('apply')) {
            $this->error('REFUSED: --apply is not available. This command is read-only.');

            return self::FAILURE;
        }

        $statuses = array_values(array_filter(array_map('intval', explode(',', (string) $this->option('status')))));
        $rows = $reconciler->inspectStatuses($statuses);
        $detector = $reconciler->paymentDetectorSnapshot();
        $dupes = $reconciler->duplicateInboundRows();
        $payloadHealth = $health->build($rows, $detector);
        $severity = $health->operationalSeverity($payloadHealth);

        $summary = [
            'read_only' => true,
            'row_count' => count($rows),
            'health' => $payloadHealth,
            'severity' => $severity,
            'duplicate_inbound_txid_count' => count($dupes),
            'detector' => $detector,
        ];

        $write = (string) $this->option('write');
        if ($write !== '') {
            File::ensureDirectoryExists($write);
            $this->writeCsv($write.'/reconciliation-rows.csv', $rows);
            File::put($write.'/funded-order-health.json', json_encode($payloadHealth, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            File::put($write.'/reconciliation-summary.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->writeCsv($write.'/duplicate-inbound-evidence.csv', $dupes);
            $this->info('Wrote artifacts to '.$write);
        }

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('rows='.count($rows));
            $this->line('paid_total='.$payloadHealth['paid_total']);
            $this->line('wh_confirmed='.$payloadHealth['waiting_handle_with_confirmed_funds']);
            $this->line('expired_with_funds='.$payloadHealth['expired_with_confirmed_funds']);
            $this->line('funded_wrong_status='.$payloadHealth['funded_wrong_status']);
            $this->line('duplicate_txid='.count($dupes));
            $this->line('severity='.$severity['severity']);
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     */
    private function writeCsv(string $path, array $rows): void
    {
        $fh = fopen($path, 'w');
        if ($fh === false) {
            throw new \RuntimeException('Cannot write '.$path);
        }
        if ($rows === []) {
            fputcsv($fh, ['empty']);
            fclose($fh);

            return;
        }
        $keys = array_keys($rows[0]);
        fputcsv($fh, $keys);
        foreach ($rows as $row) {
            $line = [];
            foreach ($keys as $k) {
                $v = $row[$k] ?? '';
                $line[] = is_scalar($v) || $v === null ? (string) $v : json_encode($v);
            }
            fputcsv($fh, $line);
        }
        fclose($fh);
    }
}
