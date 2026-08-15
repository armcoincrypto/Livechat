<?php

namespace iEXPackages\Courses\FormulaTags\Handlers;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class LimitTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        [$value, $min, $max] = array_map('trim', explode(',', $expression));

        $calculatedValue = $parser->calculate($value, $precision);
        $min = $parser->calculate($min, $precision);
        $max = $parser->calculate($max, $precision);

        if (bccomp($calculatedValue, $min, $precision) < 0) {
            return $min;
        }

        if (bccomp($calculatedValue, $max, $precision) > 0) {
            return $max;
        }

        return $calculatedValue;
    }
}
