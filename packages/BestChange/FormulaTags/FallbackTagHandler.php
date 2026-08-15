<?php

namespace iEXPackages\BestChange\FormulaTags;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;


class FallbackTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        [$primary, $fallback] = array_map('trim', explode(',', $expression));

        $primaryValue = (float)$parser->calculate("$primary", $precision);
        if ($primaryValue > 0) {
            return number_format($primaryValue, $precision, '.', '');
        }

        $fallbackValue = (float)$parser->calculate("$fallback", $precision);
        return number_format($fallbackValue, $precision, '.', '');
    }
}
