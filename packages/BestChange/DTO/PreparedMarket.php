<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\DTO;

/**
 * PreparedMarket
 *
 * Результат подготовки рынка BestChange для одной пары:
 * - сырые строки
 * - строки после фильтров
 * - строки после сортировки (финальный порядок)
 * - выбранная строка (если режим требует выбора)
 * - счетчики причин отбраковки
 * - presence (если включено)
 *
 * Важно:
 * Этот DTO специально не знает про Eloquent/БД — только данные.
 */
final readonly class PreparedMarket
{
    /**
     * @param array<int, array<string,mixed>> $rawRows
     * @param array<int, array<string,mixed>> $filteredRows
     * @param array<int, array<string,mixed>> $sortedRows
     * @param array<string,int>              $rejectCounters
     * @param array<string,mixed>|null       $presence
     * @param array<string,mixed>|null       $selectedRow
     */
    public function __construct(
        public array $rawRows,
        public array $filteredRows,
        public array $sortedRows,
        public array $rejectCounters,
        public ?array $presence,
        public ?array $selectedRow,
    ) {}
}
