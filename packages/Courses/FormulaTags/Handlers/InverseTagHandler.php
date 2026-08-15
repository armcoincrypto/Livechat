<?php

namespace iEXPackages\Courses\FormulaTags\Handlers;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class InverseTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        $rate = $parser->calculate(trim($expression), $precision);

        if (!is_numeric($rate) || bccomp($rate, '0', $precision) === 0) {
            return '0';
        }

        return bcdiv('1', $rate, $precision);
    }
}
