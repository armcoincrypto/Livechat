<?php

namespace iEXPackages\Courses\FormulaTags\Handlers;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class SpreadTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        [$bid, $ask] = array_map('trim', explode(',', $expression));

        $bidVal = $parser->calculate($bid, $precision);
        $askVal = $parser->calculate($ask, $precision);

        if (bccomp($askVal, '0', $precision) == 0) {
            return '0';
        }

        $spread = bcdiv(bcmul(bcsub($askVal, $bidVal, $precision), '100', $precision), $askVal, $precision);

        return $spread;
    }
}
