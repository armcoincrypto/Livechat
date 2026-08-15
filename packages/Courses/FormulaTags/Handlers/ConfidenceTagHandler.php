<?php

namespace iEXPackages\Courses\FormulaTags\Handlers;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class ConfidenceTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        $sources = explode(',', $expression);
        $weightedSum = '0';
        $totalWeight = '0';

        foreach ($sources as $source) {
            [$rate, $weight] = explode(':', trim($source));
            $rateVal = $parser->calculate($rate, $precision);
            $weightVal = $parser->calculate($weight, $precision);

            $weightedSum = bcadd($weightedSum, bcmul($rateVal, $weightVal, $precision), $precision);
            $totalWeight = bcadd($totalWeight, $weightVal, $precision);
        }

        return $totalWeight ? bcdiv($weightedSum, $totalWeight, $precision) : '0';
    }
}
