<?php

namespace iEXPackages\BestChange\FormulaTags;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class CrossRateTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        [$firstTag, $secondTag] = array_map('trim', explode(',', $expression));

        $firstValue = (float)$parser->calculate("$firstTag", $precision);
        $secondValue = (float)$parser->calculate("$secondTag", $precision);

        if ($secondValue == 0) return '0';

        return number_format($firstValue / $secondValue, $precision, '.', '');
    }
}
