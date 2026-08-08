<?php

declare(strict_types=1);

namespace iEXPackages\Calculator\Strategies;

use App\Models\DirectionExchange;
use iEXPackages\Calculator\Contracts\StrategyInterface;
use iEXPackages\Calculator\Traits\InteractsWithNumbers;

/**
 * Retains ZELLE_USDTTRC20_BENCHMARK automatic BASE for ZELLEUSD outgoing directions.
 *
 * Prevents the ionCube courses compiler from falling through to ManualStrategy
 * (or other sources) and rewriting the exclusive ZELLE ownership label.
 */
class ZelleBaselineStrategy implements StrategyInterface
{
    use InteractsWithNumbers;

    public function __construct(
        protected DirectionExchange $directionExchange
    ) {
    }

    public function getRate(): string
    {
        $course = $this->sanitizeNumber($this->directionExchange->course_value ?? '0');
        if ($this->isGreaterThanZero($course)) {
            return $course;
        }

        $mirror = $this->sanitizeNumber($this->directionExchange->manual_rate_value ?? '0');

        return $this->isGreaterThanZero($mirror) ? $mirror : '0';
    }
}
