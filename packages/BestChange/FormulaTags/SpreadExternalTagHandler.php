<?php

namespace iEXPackages\BestChange\FormulaTags;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;


class SpreadExternalTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        [$posTag, $externalTag] = array_map('trim', explode(',', $expression));

        $posValue = (float)$parser->calculate("[$posTag]", $precision);
        $externalValue = (float)$parser->calculate("[$externalTag]", $precision);

        if ($externalValue == 0) return '0';

        $spread = ($posValue - $externalValue) / $externalValue * 100;

        return number_format($spread, $precision, '.', '');
    }
}
