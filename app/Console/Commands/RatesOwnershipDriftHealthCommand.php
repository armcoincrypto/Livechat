<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Read-only ownership drift detector.
 *
 * Compares current BestChange/Derived/Zelle/Manual counts against the last
 * snapshot. Alerts (exit 1) on abnormal swings; never mutates rates.
 */
final class RatesOwnershipDriftHealthCommand extends Command
{
    protected $signature = 'rates:ownership-drift-health
        {--format=json : json|table}
        {--baseline-drop-abs=50 : Absolute BestChange active drop that is critical}
        {--baseline-drop-pct=5 : Percent BestChange active drop that is critical}
        {--manual-jump-abs=50 : Absolute Manual jump that is critical}
        {--derived-jump-abs=100 : Absolute Derived jump that is critical}';

    protected $description = 'Detect abnormal BestChange/Derived/Manual/Zelle ownership swings (read-only).';

    public function handle(): int
    {
        $owners = $this->currentOwners();
        $bcActive = (int) DB::table('bestchange_directions')->where('status', 1)->count();
        $bcActiveLinked = (int) DB::table('bestchange_directions as bc')
            ->join('direction_exchange as de', 'de.id', '=', 'bc.id_direction_exchange')
            ->where('bc.status', 1)
            ->where('de.status', 1)
            ->whereNull('de.deleted_at')
            ->count();

        $stateDir = storage_path('app/rates/ownership-drift');
        if (!is_dir($stateDir)) {
            File::makeDirectory($stateDir, 0755, true);
        }
        $prevPath = $stateDir.'/last.json';
        $prev = is_file($prevPath)
            ? (json_decode((string) file_get_contents($prevPath), true) ?: null)
            : null;

        $dropAbs = (int) $this->option('baseline-drop-abs');
        $dropPct = (float) $this->option('baseline-drop-pct');
        $manualJump = (int) $this->option('manual-jump-abs');
        $derivedJump = (int) $this->option('derived-jump-abs');

        $alerts = [];
        if (is_array($prev)) {
            $prevBc = (int) ($prev['bc_active_linked'] ?? 0);
            $prevBest = (int) ($prev['owners']['BESTCHANGE'] ?? 0);
            $prevManual = (int) ($prev['owners']['MANUAL'] ?? 0);
            $prevDerived = (int) ($prev['owners']['DERIVED'] ?? 0);

            $bcDrop = $prevBc - $bcActiveLinked;
            if ($bcDrop >= $dropAbs) {
                $alerts[] = sprintf('BC_ACTIVE_LINKED_DROP_ABS=%d (prev=%d now=%d)', $bcDrop, $prevBc, $bcActiveLinked);
            }
            if ($prevBc > 0 && ($bcDrop / $prevBc) * 100.0 >= $dropPct) {
                $alerts[] = sprintf(
                    'BC_ACTIVE_LINKED_DROP_PCT=%.2f (prev=%d now=%d)',
                    ($bcDrop / $prevBc) * 100.0,
                    $prevBc,
                    $bcActiveLinked
                );
            }

            $bestDrop = $prevBest - (int) ($owners['BESTCHANGE'] ?? 0);
            if ($bestDrop >= $dropAbs) {
                $alerts[] = sprintf('BESTCHANGE_OWNER_DROP_ABS=%d', $bestDrop);
            }

            $manualDelta = (int) ($owners['MANUAL'] ?? 0) - $prevManual;
            if ($manualDelta >= $manualJump) {
                $alerts[] = sprintf('MANUAL_JUMP_ABS=%d', $manualDelta);
            }

            $derivedDelta = (int) ($owners['DERIVED'] ?? 0) - $prevDerived;
            if ($derivedDelta >= $derivedJump) {
                $alerts[] = sprintf('DERIVED_JUMP_ABS=%d', $derivedDelta);
            }
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'bc_status_1' => $bcActive,
            'bc_active_linked' => $bcActiveLinked,
            'owners' => $owners,
            'previous' => $prev,
            'alerts' => $alerts,
            'critical' => $alerts !== [],
        ];

        // Always refresh snapshot after evaluation so the next tick compares
        // against this generation (detection only; no auto-repair).
        File::put($prevPath, json_encode([
            'generated_at' => $payload['generated_at'],
            'bc_status_1' => $bcActive,
            'bc_active_linked' => $bcActiveLinked,
            'owners' => $owners,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        if ($alerts !== []) {
            Log::warning('rates.ownership_drift_alert', $payload);
        }

        if ($this->option('format') === 'table') {
            $this->table(['metric', 'value'], [
                ['bc_status_1', $bcActive],
                ['bc_active_linked', $bcActiveLinked],
                ['BESTCHANGE', $owners['BESTCHANGE'] ?? 0],
                ['DERIVED', $owners['DERIVED'] ?? 0],
                ['ZELLE', $owners['ZELLE'] ?? 0],
                ['MANUAL', $owners['MANUAL'] ?? 0],
                ['alerts', implode('; ', $alerts) ?: 'none'],
            ]);
        } else {
            $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return $alerts === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<string,int>
     */
    private function currentOwners(): array
    {
        $rows = DB::table('direction_exchange')
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->selectRaw("CASE
                WHEN parser_source_name='BestChange' THEN 'BESTCHANGE'
                WHEN parser_source_name='DERIVED_MARKET_BASELINE' THEN 'DERIVED'
                WHEN parser_source_name='ZELLE_USDTTRC20_BENCHMARK' THEN 'ZELLE'
                WHEN parser_source_name IN ('Ручной курс','Manual') THEN 'MANUAL'
                ELSE 'UNKNOWN' END as owner, COUNT(*) c")
            ->groupBy('owner')
            ->pluck('c', 'owner')
            ->all();

        return [
            'BESTCHANGE' => (int) ($rows['BESTCHANGE'] ?? 0),
            'DERIVED' => (int) ($rows['DERIVED'] ?? 0),
            'ZELLE' => (int) ($rows['ZELLE'] ?? 0),
            'MANUAL' => (int) ($rows['MANUAL'] ?? 0),
            'UNKNOWN' => (int) ($rows['UNKNOWN'] ?? 0),
        ];
    }
}
