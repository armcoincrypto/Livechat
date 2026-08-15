<?php

namespace iEXPackages\Courses\FormulaTags\Handlers;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class WorstRateTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        $sources = array_map('trim', explode(',', $expression));
        $worstRate = null;

        foreach ($sources as $source) {
            $rate = $parser->calculate($source, $precision);

            if (is_numeric($rate) && bccomp($rate, '0', $precision) > 0) {
                if (is_null($worstRate) || bccomp($rate, $worstRate, $precision) < 0) {
                    $worstRate = $rate;
                }
            }
        }

        return $worstRate ?? '0';
    }
}
