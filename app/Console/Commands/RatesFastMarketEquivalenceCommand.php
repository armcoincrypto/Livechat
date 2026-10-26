<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BestChangeDirection;
use App\Services\Rates\FastMarket\BestChangeBookRateComputer;
use App\Services\Rates\FastMarket\FastMarketFlags;
use App\Services\Rates\FastMarket\MarketSnapshotStore;
use Illuminate\Console\Command;
use Throwable;

/**
 * Same-input equivalence: snapshot book → BookRateComputer vs persisted rate_value.
 *
 * Age-driven diffs are classified separately from algorithm mismatches.
 * For pure formula equivalence, feed identical books (offline fixture / forced snapshot).
 */
final class RatesFastMarketEquivalenceCommand extends Command
{
    protected $signature = 'rates:fast-market-equivalence
        {--limit=0 : Max directions (0 = all active BC)}
        {--format=json : json|text}
        {--tolerance-scale=12 : bccomp scale}';

    protected $description = 'Compare BestChangeBookRateComputer(snapshot) vs persisted rate_value';

    public function handle(
        MarketSnapshotStore $store,
        BestChangeBookRateComputer $computer,
    ): int {
        $snapshot = $store->get();
        if ($snapshot === null) {
            $this->error('No snapshot in store — run rates:fast-market-snapshot-refresh --force first');

            return self::FAILURE;
        }

        $limit = max(0, (int) $this->option('limit'));
        $scale = max(6, (int) $this->option('tolerance-scale'));

        $q = BestChangeDirection::query()
            ->where('status', 1)
            ->where('id_direction_exchange', '>', 0)
            ->orderBy('id');
        if ($limit > 0) {
            $q->limit($limit);
        }
        $dirs = $q->get();

        $checked = 0;
        $algorithmMismatch = 0;
        $ageDriven = 0;
        $skipped = 0;
        $mismatches = [];

        foreach ($dirs as $dir) {
            $deId = (int) $dir->id_direction_exchange;
            $pairKey = $snapshot->pairKeyForDirectionExchange($deId);
            $raw = $snapshot->rawRowsForDirectionExchange($deId);
            if ($pairKey === null || $raw === null) {
                $skipped++;
                continue;
            }

            $computed = $computer->compute(
                direction: $dir,
                rawRows: $raw,
                pairKey: $pairKey,
                externalRates: $snapshot->externalRates,
                exchangersMap: $snapshot->exchangersMap,
            );
            $checked++;
            if (! ($computed['ok'] ?? false)) {
                $skipped++;
                continue;
            }

            $persisted = (string) ($dir->rate_value ?? '');
            $fresh = (string) ($computed['rate_value'] ?? '');
            $equal = function_exists('bccomp')
                ? bccomp($persisted, $fresh, $scale) === 0
                : abs((float) $persisted - (float) $fresh) < 1e-12;

            if ($equal) {
                continue;
            }

            // Persisted rate may lag snapshot — classify as age-driven unless
            // updated_at is within snapshot age window (then treat as algorithm).
            $persistedAge = null;
            if (! empty($dir->updated_at)) {
                $persistedAge = max(0, time() - $dir->updated_at->getTimestamp());
            }
            $snapAge = $snapshot->ageSeconds();
            $likelyAgeDriven = $persistedAge !== null && $persistedAge > max(90, $snapAge + 30);

            if ($likelyAgeDriven) {
                $ageDriven++;
            } else {
                $algorithmMismatch++;
                if (count($mismatches) < 25) {
                    $mismatches[] = [
                        'bc_id' => (int) $dir->id,
                        'de_id' => $deId,
                        'persisted' => $persisted,
                        'fresh' => $fresh,
                        'persisted_age' => $persistedAge,
                        'snapshot_age' => $snapAge,
                    ];
                }
            }
        }

        $payload = [
            'snapshot_id' => $snapshot->snapshotId,
            'snapshot_age_seconds' => $snapshot->ageSeconds(),
            'checked' => $checked,
            'skipped' => $skipped,
            'age_driven_diffs' => $ageDriven,
            'algorithm_mismatch_count' => $algorithmMismatch,
            'mismatches_sample' => $mismatches,
            'plane_enabled' => FastMarketFlags::planeEnabled(),
            'pass' => $algorithmMismatch === 0,
        ];

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $algorithmMismatch === 0 ? self::SUCCESS : self::FAILURE;
    }
}
