<?php

namespace iEXPackages\Courses\FormulaTags\Handlers;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class FallbackTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        [$primary, $fallback] = array_map('trim', explode(',', $expression));

        $primaryValue = $parser->calculate($primary, $precision);

        return (bccomp($primaryValue, '0', $precision) == 0)
            ? $parser->calculate($fallback, $precision)
            : $primaryValue;
    }
}
