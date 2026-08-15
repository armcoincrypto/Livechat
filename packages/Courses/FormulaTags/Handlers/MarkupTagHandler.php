<?php

namespace iEXPackages\Courses\FormulaTags\Handlers;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class MarkupTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        [$rateExpr, $percent] = array_map('trim', explode(',', $expression));

        $rate = $parser->calculate($rateExpr, $precision);
        $percent = rtrim($percent, '%');

        if (!is_numeric($rate) || !is_numeric($percent)) {
            return '0';
        }

        $multiplier = bcadd('1', bcdiv($percent, '100', $precision), $precision);

        return bcmul($rate, $multiplier, $precision);
    }
}
