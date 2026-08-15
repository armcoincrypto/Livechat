<?php

namespace iEXPackages\BestChange\FormulaTags;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class ExternalFeeTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        [$tag, $feePercent] = array_map('trim', explode(',', $expression));
        $fee = (float)str_replace('%', '', $feePercent) / 100;
        $tag = trim($tag, '[]');

        // Вычисляем значение тега (без двойных скобок!)
        $value = (float)$parser->calculate("[$tag]", $precision);

        return number_format($value * (1 - $fee), $precision, '.', '');
    }
}
