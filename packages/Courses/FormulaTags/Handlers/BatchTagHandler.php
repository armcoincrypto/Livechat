<?php

namespace iEXPackages\Courses\FormulaTags\Handlers;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class BatchTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        preg_match_all('/\[formula\](.*?)\[\/formula\]/s', $expression, $matches);

        $results = [];
        foreach ($matches[1] as $formula) {
            $results[] = $parser->calculate(trim($formula), $precision);
        }

        return implode(',', $results);
    }
}
