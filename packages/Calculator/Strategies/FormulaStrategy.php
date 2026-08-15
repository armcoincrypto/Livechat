<?php
declare(strict_types=1);

namespace iEXPackages\Calculator\Strategies;

use App\Models\DirectionExchange;
use iEXPackages\Calculator\Contracts\StrategyInterface;
use iEXPackages\Calculator\Traits\InteractsWithNumbers;

class FormulaStrategy implements StrategyInterface
{
    use InteractsWithNumbers;

    public function __construct(
        protected DirectionExchange $directionExchange
    ) {}

    public function getRate(): string
    {
        $courseValue = $this->sanitizeNumber($this->directionExchange->parser_formula_rates->summa ?? '0');

        if (!$this->isGreaterThanZero($courseValue)) {
            return '0';
        }

        return $courseValue;
    }
}
