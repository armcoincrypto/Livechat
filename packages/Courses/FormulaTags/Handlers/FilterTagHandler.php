<?php

namespace iEXPackages\Courses\FormulaTags\Handlers;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class FilterTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        [$value, $filter] = array_map('trim', explode('|', $expression, 2));

        $calculatedValue = $parser->calculate($value, $precision);

        return match (strtolower($filter)) {
            'ceil'  => ceil($calculatedValue),
            'floor' => floor($calculatedValue),
            'abs'   => abs($calculatedValue),
            default => $calculatedValue,
        };
    }
}
