<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Services;

use App\Models\BestChangeDirection;
use App\Services\AggregatedRatesService;
use App\Settings\BestChangeConfig;
use iEXPackages\BestChange\Contracts\BestChangeHttpClientInterface;
use iEXPackages\BestChange\DTO\PairKey;
use iEXPackages\BestChange\DTO\RateSelectionPolicy;

/**
 * StrategySimulatorService
 *
 * Симулирует разные стратегии выбора курса на текущих данных BestChange.
 *
 * Назначение:
 * - получить /rates для одной пары
 * - прогнать разные режимы выбора (position / median_top_n / weighted_avg_top_n)
 * - вернуть структурированный результат для UI/аналитики
 *
 * Важно:
 * - это симуляция "на сейчас" (live), а не исторический backtest
 * - анти-фейк применяется через RateRowFilter (включая _quality_score/_quality_reasons)
 */
final class StrategySimulatorService
{
    private const MODES = [
        'position',
        'median_top_n',
        'weighted_avg_top_n',
    ];

    public function __construct(
        private readonly BestChangeHttpClientInterface $httpClient,
        private readonly BestChangeConfig $settings,
        private readonly RateRowFilter $rowFilter,
        private readonly RateRowSorter $rowSorter,
        private readonly RateRowSelector $rowSelector,
        private readonly RateCalculator $rateCalculator,
        private readonly RateSelectionPolicyResolver $policyResolver,
        private readonly BestChangeCatalogRepository $catalog,
        private readonly AggregatedRatesService $externalRatesService,
    ) {}

    /**
     * Симулировать стратегии для одного направления BestChange.
     *
     * @return array<string,mixed>
     */
    public function simulate(BestChangeDirection $direction): array
    {
        $from = (int)($direction->id_currency_in ?? 0);
        $to   = (int)($direction->id_currency_out ?? 0);
        $city = (int)($direction->city_id ?? 0);

        if ($from <= 0 || $to <= 0) {
            return [
                'direction_id' => (int)$direction->id,
                'error' => 'Invalid direction currencies',
                'simulations' => [],
            ];
        }

        // прогрев справочников (для sourceName и удобных данных)
        $this->catalog->warmupAll(false);

        // карта обменников: [changerId => name]
        $exchangersMap = $this->buildExchangersMap();

        // внешние курсы (для формул) — один раз на запуск симуляции
        $externalRates = $this->externalRatesService->fetch();

        $pairKey = (new PairKey($from, $to, $city))->toString();

        // получаем rates
        $ratesMap = $this->httpClient->fetchRatesBatch([$pairKey]);
        $rows = $ratesMap[$pairKey] ?? [];
        $rowsTotal = is_array($rows) ? count($rows) : 0;

        // базовая политика (typeField/sortOrder/topN/window)
        $basePolicy = $this->policyResolver->resolve($direction, $this->settings);

        $defaultPosition = max(1, (int)$this->settings->position());
        $globalBlacklist = $this->settings->blacklist();

        $simulations = [];

        foreach (self::MODES as $mode) {
            $policy = new RateSelectionPolicy(
                typeField: $basePolicy->typeField,
                sortOrder: $basePolicy->sortOrder,
                mode: $mode,
                topN: $basePolicy->topN,
                positionWindowSeconds: $basePolicy->positionWindowSeconds,
            );

            $rejectCounters = [];

            $filtered = $this->rowFilter->filter(
                rows: is_array($rows) ? $rows : [],
                direction: $direction,
                policy: $policy,
                globalBlacklist: $globalBlacklist,
                rejectCounters: $rejectCounters
            );

            $sorted = $this->rowSorter->sort($filtered, $policy);
            $selected = $this->rowSelector->select($direction, $sorted, $policy, $defaultPosition);

            $computed = null;
            $selection = null;

            if ($selected !== null) {
                $computed = $this->rateCalculator->calculate(
                    direction: $direction,
                    selectedRow: $selected->row,
                    sortedRows: $sorted,
                    policy: $policy,
                    externalRates: $externalRates,
                    exchangersMap: $exchangersMap,
                );

                $selection = [
                    'method' => $selected->method,
                    'position' => $selected->position,
                    'top_n' => $selected->topN,
                    'changer_id' => is_scalar($selected->row['changer'] ?? null) ? (int)$selected->row['changer'] : 0,
                    'quality_score' => is_scalar($selected->row['_quality_score'] ?? null) ? (int)$selected->row['_quality_score'] : null,
                    'quality_reasons' => is_array($selected->row['_quality_reasons'] ?? null) ? $selected->row['_quality_reasons'] : null,
                ];
            }

            $simulations[$mode] = [
                'rows_total' => $rowsTotal,
                'rows_after_filter' => count($filtered),
                'rejected' => $rejectCounters,
                'selection' => $selection,
                'computed' => $computed ? [
                    'rate' => $computed->rateValue,
                    'rate_without_step' => $computed->rateValueWithoutStep,
                    'source_name' => $computed->sourceName,
                ] : null,
            ];
        }

        return [
            'direction_id' => (int)$direction->id,
            'pair_key' => $pairKey,
            'type_field' => $basePolicy->typeField,
            'sort_order' => $basePolicy->sortOrder,
            'top_n' => $basePolicy->topN,
            'simulations' => $simulations,
        ];
    }

    /**
     * Построить карту обменников [changerId => name].
     *
     * @return array<int,string>
     */
    private function buildExchangersMap(): array
    {
        $rows = $this->catalog->exchangers(false);

        $map = [];
        foreach ($rows as $id => $row) {
            $id = (int)$id;
            if ($id <= 0) continue;

            $map[$id] = (string)($row['name'] ?? '');
        }

        return $map;
    }
}
