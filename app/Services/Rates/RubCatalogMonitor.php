<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Models\DirectionExchange;
use Illuminate\Support\Facades\Log;

/**
 * Records RUB catalog movement without changing direction eligibility.
 */
final class RubCatalogMonitor
{
    /**
     * @return array<string,mixed>
     */
    public function capture(): array
    {
        $snapshot = IndependentMarketBaseline::beginSnapshot(purpose: 'monitor');

        try {
            $directions = DirectionExchange::query()
                ->where('status', 1)
                ->whereHas('currency2', static fn ($q) => $q->where('designation_xml', 'like', '%RUB%'))
                ->with(['currency1', 'currency2'])
                ->orderBy('id')
                ->get();

            $eligibility = RateDirectionEligibility::make();
            $rows = [];
            foreach ($directions as $direction) {
                $result = $eligibility->evaluateDirection($direction);
                $rows[(string) $direction->id] = [
                    'direction_id' => (int) $direction->id,
                    'pair' => $result['from'] . '->' . $result['to'],
                    'classification' => $result['classification'],
                    'export_allowed' => (bool) $result['export_allowed'],
                    'reasons' => $result['blocking_reasons'],
                    'baseline_provider' => $result['baseline_provider'] ?? null,
                    'baseline_age_seconds' => $result['baseline_age_seconds'] ?? null,
                    'circular_source_detected' => $result['circular_source_detected'] ?? false,
                ];
            }

            $latestPath = storage_path('app/rates/rub-catalog-monitor-latest.json');
            $historyPath = storage_path('app/rates/rub-catalog-monitor-history.jsonl');
            $previous = is_file($latestPath)
                ? json_decode((string) file_get_contents($latestPath), true)
                : null;
            $previousRows = is_array($previous['directions'] ?? null) ? $previous['directions'] : [];

            $currentEligible = array_keys(array_filter(
                $rows,
                static fn (array $row): bool => $row['export_allowed'],
            ));
            $previousEligible = array_keys(array_filter(
                $previousRows,
                static fn (array $row): bool => !empty($row['export_allowed']),
            ));
            $added = array_values(array_diff($currentEligible, $previousEligible));
            $removed = array_values(array_diff($previousEligible, $currentEligible));
            $changedCount = count($added) + count($removed);
            $referenceCount = max(1, count($previousEligible));
            $material = $previous !== null
                && ($changedCount > 5 || ($changedCount / $referenceCount) > 0.10);

            $classificationCounts = [];
            foreach ($rows as $row) {
                $key = (string) ($row['classification'] ?? 'UNKNOWN');
                $classificationCounts[$key] = ($classificationCounts[$key] ?? 0) + 1;
            }

            $record = [
                'schema' => 'rub_catalog_monitor_v1',
                'generated_at_utc' => gmdate('c'),
                'snapshot_id' => $snapshot['id'],
                'snapshot_timestamp' => $snapshot['captured_at'],
                'current_rub_export_count' => count($currentEligible),
                'classification_counts' => $classificationCounts,
                'directions_added' => array_values(array_intersect_key($rows, array_flip($added))),
                'directions_removed' => array_values(array_intersect_key($previousRows, array_flip($removed))),
                'material_change' => $material,
                'thresholds' => ['absolute' => 5, 'percent' => 10],
                'directions' => $rows,
            ];

            $directory = dirname($latestPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0770, true);
            }
            $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $tmp = $latestPath . '.tmp.' . getmypid();
            file_put_contents($tmp, $json . PHP_EOL, LOCK_EX);
            rename($tmp, $latestPath);
            file_put_contents(
                $historyPath,
                json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
                FILE_APPEND | LOCK_EX,
            );

            if ($material) {
                Log::warning('Material RUB export catalog change', [
                    'snapshot_id' => $snapshot['id'],
                    'previous_count' => count($previousEligible),
                    'current_count' => count($currentEligible),
                    'added' => $added,
                    'removed' => $removed,
                ]);
            }

            return $record;
        } finally {
            IndependentMarketBaseline::endSnapshot();
        }
    }
}
