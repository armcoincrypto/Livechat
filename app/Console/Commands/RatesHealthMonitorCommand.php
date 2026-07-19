<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rates\IndependentMarketBaseline;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Read-only rate pipeline health monitor. Exit 1 on critical conditions.
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

        // lightweight unexplained extremes via last audit file if present
        $auditPath = storage_path('app/rates_health_last_audit.json');
        $extreme = null;
        $critical = null;
        $noBaseline = null;
        if (is_file($auditPath)) {
            $audit = json_decode((string) file_get_contents($auditPath), true);
            $extreme = $audit['unexplained_deviation_counts']['extreme'] ?? null;
            $critical = $audit['unexplained_deviation_counts']['critical'] ?? null;
            $noBaseline = $audit['unexplained_deviation_counts']['no_baseline'] ?? null;
        }

        $coverage = $baseline->coverage();
        $staleBaselines = count($coverage['gaps'] ?? []);

        // XML mutation flag
        $xmlChanger = base_path('xml-changer/main.py');
        $mutateDisabled = is_file($xmlChanger) && str_contains((string) file_get_contents($xmlChanger), 'EXSWAPING_XML_CHANGER_MUTATE_RATES');

        $criticalHit = $invalidActive > 0
            || (is_int($extreme) && $extreme > 0)
            || (is_int($critical) && $critical > 0)
            || !$mutateDisabled;

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'invalid_active_rate_count' => $invalidActive,
            'quarantined_direction_count' => $quarantined,
            'unexplained_extreme_count' => $extreme,
            'unexplained_critical_count' => $critical,
            'no_baseline_active_count' => $noBaseline,
            'stale_baseline_gap_count' => $staleBaselines,
            'baseline_gaps' => $coverage['gaps'],
            'xml_mutator_guard_present' => $mutateDisabled,
            'critical' => $criticalHit,
        ];

        Log::info('rates:health', $payload);

        if ((string) $this->option('format') === 'table') {
            $this->table(['metric', 'value'], collect($payload)->except(['baseline_gaps'])->map(fn ($v, $k) => [$k, is_bool($v) ? ($v ? 'true' : 'false') : (string) $v])->values()->all());
        } else {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return $criticalHit ? self::FAILURE : self::SUCCESS;
    }
}
