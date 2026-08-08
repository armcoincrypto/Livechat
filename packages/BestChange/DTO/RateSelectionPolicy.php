<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\DTO;

/**
 * RateSelectionPolicy
 *
 * Настройки выбора курса для направления:
 * - typeField: rate|rankrate
 * - sortOrder: api|asc|desc
 * - mode: position|median_top_n|weighted_avg_top_n
 * - topN: размер TOP-N (для median/weighted)
 * - positionWindowSeconds: окно стабилизации диапазона позиции "3-7"
 */
final readonly class RateSelectionPolicy
{
    public function __construct(
        public string $typeField,
        public string $sortOrder,
        public string $mode,
        public int $topN,
        public int $positionWindowSeconds,
    ) {}
}
