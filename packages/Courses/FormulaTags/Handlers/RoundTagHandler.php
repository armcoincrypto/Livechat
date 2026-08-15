<?php

namespace iEXPackages\Courses\FormulaTags\Handlers;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class RoundTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        [$digits, $expr] = explode(',', $expression, 2);

        $value = $parser->calculate(trim($expr), $precision);
        $digits = (int)trim($digits);

        return number_format((float)$value, $digits, '.', '');
    }
}
