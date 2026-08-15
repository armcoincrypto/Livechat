<?php

namespace iEXPackages\Courses\FormulaTags\Handlers;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class SetVariableTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        [$variable, $value] = array_map('trim', explode('=', $expression, 2));

        $calculatedValue = $parser->calculate($value, $precision);

        $parser->setVariable(trim($variable), $calculatedValue);

        return ''; // <-- верни пустую строку!
    }
}
