<?php

namespace iEXPackages\Courses\FormulaTags\Handlers;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class MinTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        $items = explode(',', $expression);
        $min = null;

        foreach ($items as $item) {
            $value = $parser->calculate(trim($item), $precision);
            if (is_null($min) || bccomp($value, $min, $precision) < 0) {
                $min = $value;
            }
        }

        return $min ?? '0';
    }
}
