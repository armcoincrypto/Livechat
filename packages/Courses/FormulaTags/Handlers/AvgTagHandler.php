<?php

namespace iEXPackages\Courses\FormulaTags\Handlers;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class AvgTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        $items = explode(',', $expression);
        $sum = '0';
        $count = 0;

        foreach ($items as $item) {
            $value = $parser->calculate(trim($item), $precision);
            $sum = bcadd($sum, $value, $precision);
            $count++;
        }

        return $count ? bcdiv($sum, (string)$count, $precision) : '0';
    }
}
