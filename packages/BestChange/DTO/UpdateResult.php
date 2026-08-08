<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\DTO;

/**
 * UpdateResult
 *
 * Результат выполнения обновления BestChange.
 *
 * Совместимость:
 * - $updatedCount / $totalPairsCount — старые имена (если где-то уже используются)
 *
 * Актуальные поля:
 * - $updatedDirections — сколько направлений обновлено успешно
 * - $totalDirections   — сколько направлений было обработано
 * - $uniquePairs       — сколько уникальных pairKey было сформировано/запрошено
 */
final readonly class UpdateResult
{
    /**
     * Старое имя (совместимость): сколько направлений обновили успешно.
     */
    public int $updatedCount;

    /**
     * Старое имя (совместимость): сколько пар (pairKey) было обработано.
     */
    public int $totalPairsCount;

    public function __construct(
        public int $updatedDirections,
        public int $totalDirections,
        public int $uniquePairs,
    ) {
        // алиасы для обратной совместимости
        $this->updatedCount = $updatedDirections;
        $this->totalPairsCount = $uniquePairs;
    }
}
