<?php

namespace iEXPackages\Courses\FormulaTags\Handlers;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class BestRateTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        $sources = array_map('trim', explode(',', $expression));
        $bestRate = null;

        foreach ($sources as $source) {
            $rate = $parser->calculate($source, $precision);

            if (is_numeric($rate) && bccomp($rate, '0', $precision) > 0) {
                if (is_null($bestRate) || bccomp($rate, $bestRate, $precision) > 0) {
                    $bestRate = $rate;
                }
            }
        }

        return $bestRate ?? '0';
    }
}
