<?php

namespace iEXPackages\Courses\FormulaTags\Handlers;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class CombineTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        $sources = explode(',', $expression);
        $combined = '0';

        foreach ($sources as $source) {
            [$rate, $weight] = explode(':', trim($source));
            $rateVal = $parser->calculate($rate, $precision);
            $weightVal = $parser->calculate($weight, $precision);

            $combined = bcadd($combined, bcmul($rateVal, $weightVal, $precision), $precision);
        }

        return $combined;
    }
}
