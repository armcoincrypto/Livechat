<?php

namespace iEXPackages\BestChange\FormulaTags;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class LimitTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        [$posTag, $externalTag, $percent] = array_map('trim', explode(',', $expression));
        $percent = str_replace('%', '', $percent) / 100;

        $posValue = (float)$parser->calculate("[$posTag]", $precision);
        $externalValue = (float)$parser->calculate("[$externalTag]", $precision);

        // Определяем, это max или min лимит
        if (str_starts_with($expression, 'max:')) {
            $limitValue = $externalValue * (1 + $percent);
            $finalValue = min($posValue, $limitValue);
        } else { // min:
            $limitValue = $externalValue * (1 - $percent);
            $finalValue = max($posValue, $limitValue);
        }

        return number_format($finalValue, $precision, '.', '');
    }
}
