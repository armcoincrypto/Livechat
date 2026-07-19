<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\DTO;

/**
 * ComputedRate
 *
 * Итог вычисления курса для BestChangeDirection.
 */
final readonly class ComputedRate
{
    public function __construct(
        public string $rateValue,
        public string $rateValueWithoutStep,
        public string $sourceName,
    ) {}
}
