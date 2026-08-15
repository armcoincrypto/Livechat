<?php

namespace iEXPackages\Courses\FormulaTags\Handlers;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class RatioTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        [$val1, $val2] = array_map('trim', explode(',', $expression));

        $value1 = $parser->calculate($val1, $precision);
        $value2 = $parser->calculate($val2, $precision);

        if (bccomp($value2, '0', $precision) == 0) {
            return '0';
        }

        return bcdiv($value1, $value2, $precision);
    }
}
