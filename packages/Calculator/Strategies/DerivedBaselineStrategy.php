<?php

declare(strict_types=1);

namespace iEXPackages\Calculator\Strategies;

use App\Models\DirectionExchange;
use iEXPackages\Calculator\Contracts\StrategyInterface;
use iEXPackages\Calculator\Traits\InteractsWithNumbers;

/**
 * Retains the automatic BASE already owned by DERIVED_MARKET_BASELINE.
 *
 * The ionCube courses compiler picks the first matching calculator source and
 * rewrites parser_source_name from that source's name. Without this strategy,
 * derived-owned directions fall through to ManualStrategy and get labelled
 * «Ручной курс» even though course_value remains the automatic BASE.
 */
class DerivedBaselineStrategy implements StrategyInterface
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
