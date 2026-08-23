<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Services;

use App\Models\BestChangeDirection;
use iEXPackages\BestChange\DTO\PreparedMarket;
use iEXPackages\BestChange\DTO\RateSelectionPolicy;

/**
 * BestChangeMarketPipeline
 *
 * Единый пайплайн подготовки рынка (update и rating должны использовать его одинаково).
 *
 * Делает:
 * - presence (опционально)
 * - filter (anti-fake/pool/black/white/limits + counters)
 * - sort (детерминированно)
 * - select (position/median/weighted)
 */
final class BestChangeMarketPipeline
{
    public function __construct(
        private readonly PresencesService $presences,
        private readonly RateRowFilter $filter,
        private readonly RateRowSorter $sorter,
        private readonly RateRowSelector $selector,
    ) {}

    /**
     * @param array<int, array<string,mixed>> $rawRows
     * @param array<int, mixed> $globalBlacklist
     */
    public function prepare(
        BestChangeDirection $direction,
        array $rawRows,
        RateSelectionPolicy $policy,
        int $defaultPosition,
        array $globalBlacklist,
        string $pairKey,
        bool $withPresence = true,
        int $presenceTtlSeconds = 60,
    ): PreparedMarket {
        $presence = null;
        if ($withPresence) {
            $presence = $this->presences->get($pairKey, $presenceTtlSeconds);
        }

        /** @var array<string,int> $rejectCounters */
        $rejectCounters = [];

        $filtered = $this->filter->filter(
            rows: $rawRows,
            direction: $direction,
            policy: $policy,
            globalBlacklist: $globalBlacklist,
            rejectCounters: $rejectCounters
        );

        $sorted = $this->sorter->sort($filtered, $policy);

        $selected = $this->selector->select($direction, $sorted, $policy, $defaultPosition);

        $skipReason = null;
        $requestedPosition = null;
        $selectedRow = $selected?->row;
        if ($selected !== null && $selected->method === 'position_insufficient_depth') {
            $skipReason = 'position_insufficient_depth';
            $requestedPosition = $selected->position;
            $selectedRow = null;
        }

        return new PreparedMarket(
            rawRows: array_values($rawRows),
            filteredRows: array_values($filtered),
            sortedRows: array_values($sorted),
            rejectCounters: $rejectCounters,
            presence: $presence,
            selectedRow: $selectedRow,
            skipReason: $skipReason,
            requestedPosition: $requestedPosition,
        );
    }
}
