<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Orders\Reconciliation\FundedOrderHealthBuilder;
use App\Services\Orders\Reconciliation\FundedOrderReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Fast read-only funded backlog health. Writes JSON; optional log-only alerts with dedupe.
 */
final class OrdersFundedHealthCommand extends Command
{
    protected $signature = 'orders:funded-health
        {--write= : Path to funded-order-health.json}
        {--fail-on-critical : Exit 1 on CRITICAL operational conditions}
        {--alert : Emit deduped log alerts (no new credentials)}';

    protected $description = 'Read-only funded-order health JSON (no mutations).';

    public function handle(FundedOrderReconciler $reconciler, FundedOrderHealthBuilder $builder): int
    {
        $rows = $reconciler->inspectStatuses([1, 3, 7, 4]);
        $detector = $reconciler->paymentDetectorSnapshot();
        $health = $builder->build($rows, $detector);
        $sev = $builder->operationalSeverity($health);
        $health['operational_severity'] = $sev['severity'];
        $health['operational_reasons'] = $sev['reasons'];
        $health['thresholds_are_internal_ops_defaults_not_customer_sla'] = true;

        $write = (string) ($this->option('write') ?: storage_path('app/orders/funded-order-health.json'));
        File::ensureDirectoryExists(dirname($write));
        File::put($write, json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->line(json_encode($health, JSON_UNESCAPED_SLASHES));

        if ($this->option('alert')) {
            $this->maybeAlert($sev, $health);
        }

        if ($this->option('fail-on-critical') && $sev['severity'] === 'CRITICAL') {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{severity:string,reasons:list<string>}  $sev
     * @param  array<string,mixed>  $health
     */
    private function maybeAlert(array $sev, array $health): void
    {
        $statePath = storage_path('app/orders/funded-alert-state.json');
        $prev = is_file($statePath) ? json_decode((string) file_get_contents($statePath), true) : [];
        $fingerprint = sha1(json_encode([
            $sev['severity'],
            $sev['reasons'],
            $health['waiting_handle_with_confirmed_funds'] ?? 0,
            $health['expired_with_confirmed_funds'] ?? 0,
            $health['funded_wrong_status'] ?? 0,
            $health['paid_recent'] ?? 0,
            $health['payment_detector_state'] ?? '',
        ]));
        if (($prev['fingerprint'] ?? null) === $fingerprint) {
            return;
        }
        $payload = [
            'event' => 'funded_order_health',
            'severity' => $sev['severity'],
            'reasons' => $sev['reasons'],
            'paid_total' => $health['paid_total'] ?? 0,
            'wh_confirmed' => $health['waiting_handle_with_confirmed_funds'] ?? 0,
            'expired_with_funds' => $health['expired_with_confirmed_funds'] ?? 0,
        ];
        if ($sev['severity'] === 'CRITICAL') {
            Log::critical('funded_order_health', $payload);
        } elseif ($sev['severity'] === 'WARNING') {
            Log::warning('funded_order_health', $payload);
        } elseif ($sev['severity'] === 'INFO') {
            Log::info('funded_order_health', $payload);
        } elseif (($prev['severity'] ?? null) && ($prev['severity'] !== 'OK') && $sev['severity'] === 'OK') {
            Log::info('funded_order_health_recovered', $payload);
        }
        File::ensureDirectoryExists(dirname($statePath));
        File::put($statePath, json_encode([
            'fingerprint' => $fingerprint,
            'severity' => $sev['severity'],
            'at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT));
    }
}
