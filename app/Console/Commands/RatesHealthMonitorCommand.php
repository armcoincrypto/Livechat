<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rates\IndependentMarketBaseline;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Read-only rate pipeline health monitor. Exit 1 on critical conditions only.
 */
final class RatesHealthMonitorCommand extends Command
{
    protected $signature = 'rates:health
        {--format=json : json|table}';

    protected $description = 'Read-only health checks for rate pipeline (no mutations).';

    public function handle(IndependentMarketBaseline $baseline): int
    {
        $invalidActive = (int) DB::table('direction_exchange')
            ->where('status', 1)->whereNull('deleted_at')
            ->whereRaw('CAST(course_value AS DECIMAL(36,18)) <= 0')
            ->count();

        $quarantined = (int) DB::table('direction_exchange')
            ->where('status', 0)->where('allow_export', 2)->whereNull('deleted_at')->count();

        $auditPath = storage_path('app/rates_health_last_audit.json');
        $extreme = null;
        $critical = null;
        $high = null;
        $noBaseline = null;
        $xmlParity = null;
        $restoredRegression = null;
        if (is_file($auditPath)) {
            $audit = json_decode((string) file_get_contents($auditPath), true);
            if (is_array($audit)) {
                $counts = $audit['unexplained_deviation_counts'] ?? [];
                $extreme = $counts['extreme'] ?? null;
                $critical = $counts['critical'] ?? null;
                $high = $counts['high'] ?? null;
                $noBaseline = $counts['no_baseline'] ?? ($audit['unexplained_active_no_baseline'] ?? null);
                $xmlParity = $audit['xml_parity_mismatch_count'] ?? ($audit['surface']['xml_parity_mismatches'] ?? null);
                $restoredRegression = $audit['restored_regression_count'] ?? null;
            }
        }

        $coverage = $baseline->coverage();
        $staleBaselines = count($coverage['gaps'] ?? []);

        $deployPath = storage_path('app/rates_deploy_verify_last.json');
        $runtimeDrift = null;
        if (is_file($deployPath)) {
            $deploy = json_decode((string) file_get_contents($deployPath), true);
            if (is_array($deploy)) {
                $runtimeDrift = $deploy['drift'] ?? null;
            }
        }

        $providers = $this->providerSummary();
        $mappingDrift = $this->mappingDriftCount();

        $xmlChanger = base_path('xml-changer/main.py');
        $mutateDisabled = is_file($xmlChanger) && str_contains((string) file_get_contents($xmlChanger), 'EXSWAPING_XML_CHANGER_MUTATE_RATES');

        $criticalHit = $invalidActive > 0
            || (is_int($extreme) && $extreme > 0)
            || (is_int($critical) && $critical > 0)
            || (is_int($runtimeDrift) && $runtimeDrift > 0)
            || !$mutateDisabled;

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'runtime_drift' => $runtimeDrift,
            'invalid_active_rate_count' => $invalidActive,
            'active_no_baseline_count' => $noBaseline,
            'unexplained_high_count' => $high,
            'unexplained_critical_count' => $critical,
            'unexplained_extreme_count' => $extreme,
            'stale_provider_count' => $providers['stale_provider_count'],
            'failing_provider_count' => $providers['failing_provider_count'],
            'disabled_provider_count' => $providers['disabled_provider_count'],
            'mapping_drift_count' => $mappingDrift,
            'xml_parity_count' => $xmlParity,
            'quarantined_direction_count' => $quarantined,
            'restored_regression_count' => $restoredRegression,
            'stale_baseline_gap_count' => $staleBaselines,
            'baseline_gaps' => $coverage['gaps'],
            'providers' => $providers['groups'],
            'provider_warnings' => $providers['warnings'],
            'xml_mutator_guard_present' => $mutateDisabled,
            'critical' => $criticalHit,
        ];

        // Compact log: CMC plan failures should be visible once, not flood.
        Log::info('rates:health', [
            'critical' => $criticalHit,
            'invalid_active_rate_count' => $invalidActive,
            'quarantined_direction_count' => $quarantined,
            'failing_provider_count' => $providers['failing_provider_count'],
            'disabled_provider_count' => $providers['disabled_provider_count'],
            'provider_warnings' => $providers['warnings'],
            'baseline_gaps' => $coverage['gaps'],
        ]);

        if ((string) $this->option('format') === 'table') {
            $this->table(
                ['metric', 'value'],
                collect($payload)
                    ->except(['baseline_gaps', 'providers', 'provider_warnings'])
                    ->map(fn ($v, $k) => [$k, is_bool($v) ? ($v ? 'true' : 'false') : (string) (is_array($v) ? json_encode($v) : $v)])
                    ->values()
                    ->all()
            );
        } else {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return $criticalHit ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{
     *   groups: list<array<string,mixed>>,
     *   warnings: list<string>,
     *   stale_provider_count: int,
     *   failing_provider_count: int,
     *   disabled_provider_count: int
     * }
     */
    private function providerSummary(): array
    {
        $groups = [];
        $warnings = [];
        $stale = 0;
        $failing = 0;
        $disabled = 0;

        foreach (DB::table('group_parser_exchange')->orderBy('id')->get() as $g) {
            $alias = (string) $g->alias;
            $status = (int) $g->status;
            $errors = (int) ($g->last_errors ?? 0);
            $total = (int) ($g->last_total ?? 0);
            $updated = (int) ($g->last_updated ?? 0);
            $row = [
                'id' => (int) $g->id,
                'alias' => $alias,
                'status' => $status,
                'last_total' => $total,
                'last_updated' => $updated,
                'last_errors' => $errors,
                'health' => 'ok',
            ];

            if ($status === 0) {
                $disabled++;
                $row['health'] = 'disabled';
                if ($alias === 'coinmarketcap' && $errors > 0 && $errors === $total && $total > 0) {
                    $warnings[] = 'coinmarketcap_disabled_plan_or_credential_failure';
                }
                if ($alias === 'rapira') {
                    $warnings[] = 'rapira_disabled_stale_ton_source';
                }
            } elseif ($total > 0 && $errors === $total) {
                $failing++;
                $row['health'] = 'failing';
                if ($alias === 'coinmarketcap') {
                    $warnings[] = 'coinmarketcap_failing_disable_required';
                }
            } elseif ($total > 0 && $updated === 0) {
                $stale++;
                $row['health'] = 'stale';
            }

            $groups[] = $row;
        }

        // TON baseline explicit status
        $tonFresh = (int) DB::table('parser_exchange')
            ->where('status', 1)
            ->where('is_not_update', 0)
            ->where(function ($q) {
                $q->where('code', 'like', '%ton%usdt%')
                    ->orWhere('code', 'like', '%ton-usdt%')
                    ->orWhere('name', 'like', 'TONUSDT%')
                    ->orWhere('name', 'like', 'TON USDT%');
            })
            ->where('updated_at', '>=', now()->subMinutes(30))
            ->count();
        if ($tonFresh === 0) {
            $warnings[] = 'ton_baseline_unavailable_keep_quarantined';
        }

        return [
            'groups' => $groups,
            'warnings' => array_values(array_unique($warnings)),
            'stale_provider_count' => $stale,
            'failing_provider_count' => $failing,
            'disabled_provider_count' => $disabled,
        ];
    }

    private function mappingDriftCount(): int
    {
        $path = storage_path('app/bestchange_mapping_verification.json');
        if (!is_file($path)) {
            return 0;
        }
        $raw = json_decode((string) file_get_contents($path), true);
        if (!is_array($raw) || !isset($raw['mappings']) || !is_array($raw['mappings'])) {
            return 0;
        }
        $n = 0;
        foreach ($raw['mappings'] as $m) {
            if (($m['status'] ?? '') === 'DRIFTED') {
                $n++;
            }
        }

        return $n;
    }
}
