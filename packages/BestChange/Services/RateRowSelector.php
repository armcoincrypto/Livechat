<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Services;

use App\Models\BestChangeDirection;
use iEXPackages\BestChange\DTO\RateSelectionPolicy;
use iEXPackages\BestChange\DTO\SelectedRateRow;
use iEXPackages\Calculator\Traits\InteractsWithNumbers;

/**
 * RateRowSelector
 *
 * Выбирает одну строку из массива rates после фильтрации и сортировки.
 *
 * Режимы:
 * - position              : берём строку по позиции (с поддержкой диапазона)
 * - median_top_n          : выбираем строку, ближайшую к медиане значений TOP-N
 * - weighted_avg_top_n    : выбираем строку, ближайшую к средневзвешенному TOP-N по reserve
 *
 * Важно:
 * - Не используем float: все сравнения делаются в SCALE через BigDecimal (InteractsWithNumbers).
 */
final class RateRowSelector
{
    use InteractsWithNumbers;

    private const SCALE = 18;

    public function __construct(private readonly PositionResolver $positions) {}

    /**
     * @param array<int, array<string,mixed>> $sortedRows
     */
    public function select(
        BestChangeDirection $direction,
        array $sortedRows,
        RateSelectionPolicy $policy,
        int $defaultPosition
    ): ?SelectedRateRow {
        if ($sortedRows === []) {
            return null;
        }

        return match ($policy->mode) {
            'median_top_n' => $this->selectClosestToMedianTopN($sortedRows, $policy),
            'weighted_avg_top_n' => $this->selectClosestToWeightedAvgTopN($sortedRows, $policy),
            default => $this->selectByPosition($direction, $sortedRows, $policy, $defaultPosition),
        };
    }

    /**
     * @param array<int, array<string,mixed>> $rows
     */
    private function selectByPosition(
        BestChangeDirection $direction,
        array $rows,
        RateSelectionPolicy $policy,
        int $defaultPosition
    ): SelectedRateRow {
        $pos = $this->positions->resolve($direction, count($rows), $defaultPosition, $policy->positionWindowSeconds);

        return new SelectedRateRow(
            row: $rows[$pos - 1] ?? $rows[array_key_last($rows)],
            method: 'position',
            position: $pos,
        );
    }

    /**
     * Выбор строки, ближайшей к медиане TOP-N (точно, без float).
     *
     * @param array<int, array<string,mixed>> $rows
     */
    private function selectClosestToMedianTopN(array $rows, RateSelectionPolicy $policy): SelectedRateRow
    {
        $n = min(count($rows), max(1, $policy->topN));
        $slice = array_slice($rows, 0, $n);

        $values = [];
        foreach ($slice as $r) {
            $raw = $r[$policy->typeField] ?? '0';
            $values[] = $this->toBcString(is_scalar($raw) ? $raw : '0', self::SCALE);
        }

        // sort values asc
        usort($values, fn(string $a, string $b) => $this->compareValues($a, $b, self::SCALE));

        $median = $values[(int) floor(($n - 1) / 2)] ?? '0';

        // find row with minimal abs(value - median)
        $bestIdx = 0;
        $bestDiff = null;

        foreach ($slice as $i => $r) {
            $raw = $r[$policy->typeField] ?? '0';
            $v = $this->toBcString(is_scalar($raw) ? $raw : '0', self::SCALE);

            $diff = $this->absDiff($v, $median);

            if ($bestDiff === null || $this->compareValues($diff, $bestDiff, self::SCALE) === -1) {
                $bestDiff = $diff;
                $bestIdx = $i;
            }
        }

        return new SelectedRateRow(
            row: $slice[$bestIdx],
            method: 'median_top_n',
            topN: $n,
        );
    }

    /**
     * Выбор строки, ближайшей к средневзвешенному TOP-N по reserve (точно, без float).
     *
     * Вес = max(1, reserve).
     *
     * @param array<int, array<string,mixed>> $rows
     */
    private function selectClosestToWeightedAvgTopN(array $rows, RateSelectionPolicy $policy): SelectedRateRow
    {
        $n = min(count($rows), max(1, $policy->topN));
        $slice = array_slice($rows, 0, $n);

        // sum(value * w) / sum(w)
        $sumW = '0';
        $sum = '0';

        foreach ($slice as $r) {
            $rawV = $r[$policy->typeField] ?? '0';
            $v = $this->toBcString(is_scalar($rawV) ? $rawV : '0', self::SCALE);

            $rawReserve = $r['reserve'] ?? '0';
            $reserve = $this->toBcString(is_scalar($rawReserve) ? $rawReserve : '0', self::SCALE);

            // w = max(1, reserve)
            $w = $this->compareValues($reserve, '1', self::SCALE) === -1 ? '1' : $reserve;

            $sumW = bcadd($sumW, $w, self::SCALE);
            $sum = bcadd($sum, bcmul($v, $w, self::SCALE), self::SCALE);
        }

        $avg = $this->compareValues($sumW, '0', self::SCALE) === 1
            ? bcdiv($sum, $sumW, self::SCALE)
            : '0';

        // find row with minimal abs(value - avg)
        $bestIdx = 0;
        $bestDiff = null;

        foreach ($slice as $i => $r) {
            $raw = $r[$policy->typeField] ?? '0';
            $v = $this->toBcString(is_scalar($raw) ? $raw : '0', self::SCALE);

            $diff = $this->absDiff($v, $avg);

            if ($bestDiff === null || $this->compareValues($diff, $bestDiff, self::SCALE) === -1) {
                $bestDiff = $diff;
                $bestIdx = $i;
            }
        }

        return new SelectedRateRow(
            row: $slice[$bestIdx],
            method: 'weighted_avg_top_n',
            topN: $n,
        );
    }

    /**
     * Абсолютная разница |a-b| в SCALE.
     */
    private function absDiff(string $a, string $b): string
    {
        $cmp = $this->compareValues($a, $b, self::SCALE);
        if ($cmp === 0) return '0';
        return $cmp === 1 ? bcsub($a, $b, self::SCALE) : bcsub($b, $a, self::SCALE);
    }
}
