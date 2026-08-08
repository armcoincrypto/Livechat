<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\DTO;

/**
 * PairKey
 *
 * Ключ пары BestChange: "from-to" или "from-to-city".
 */
final readonly class PairKey
{
    public function __construct(
        public int $fromCurrencyId,
        public int $toCurrencyId,
        public int $cityId = 0,
    ) {}

    public function toString(): string
    {
        return $this->cityId > 0
            ? "{$this->fromCurrencyId}-{$this->toCurrencyId}-{$this->cityId}"
            : "{$this->fromCurrencyId}-{$this->toCurrencyId}";
    }
}
