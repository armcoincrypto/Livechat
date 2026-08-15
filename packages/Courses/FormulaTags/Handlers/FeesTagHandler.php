<?php

namespace iEXPackages\Courses\FormulaTags\Handlers;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class FeesTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        [$value, $percent] = array_map('trim', explode(',', $expression));

        $baseValue = $parser->calculate($value, $precision);
        $percent = str_replace('%', '', $percent);
        $fee = bcmul($baseValue, bcdiv($percent, '100', $precision), $precision);

        return $fee;
    }
}
