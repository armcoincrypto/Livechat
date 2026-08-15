<?php

namespace iEXPackages\BestChange\FormulaTags;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;


class RoundTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        [$tag, $roundPrecision] = explode(',', $expression);

        $value = (float)$parser->calculate($tag, $precision);

        return number_format(round($value, (int)$roundPrecision), (int)$roundPrecision, '.', '');
    }
}
