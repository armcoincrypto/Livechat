<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\DTO;

/**
 * SelectedRateRow
 *
 * Результат выбора строки из списка rates.
 */
final readonly class SelectedRateRow
{
    /**
     * @param array $row Одна строка rates (как вернул BestChange API)
     */
    public function __construct(
        public array $row,
        public string $method,      // position|median_top_n|weighted_avg_top_n
        public ?int $position = null,
        public ?int $topN = null,
    ) {}
}
