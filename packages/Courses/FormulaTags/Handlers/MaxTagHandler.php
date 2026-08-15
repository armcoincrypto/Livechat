<?php

namespace iEXPackages\Courses\FormulaTags\Handlers;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class MaxTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        $items = explode(',', $expression);
        $max = null;

        foreach ($items as $item) {
            $value = $parser->calculate(trim($item), $precision);
            if (is_null($max) || bccomp($value, $max, $precision) > 0) {
                $max = $value;
            }
        }

        return $max ?? '0';
    }
}
