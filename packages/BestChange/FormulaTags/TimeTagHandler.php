<?php

namespace iEXPackages\BestChange\FormulaTags;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class TimeTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        return match ($expression) {
            'minute'      => (string)((int)date('i')),
            'hour'        => (string)((int)date('G')),
            'day'         => (string)((int)date('j')),
            'month'       => (string)((int)date('n')),
            'year'        => (string)((int)date('Y')),
            'weekday'     => (string)((int)date('N')),
            'timestamp'   => (string)time(),
            default       => '0'
        };
    }
}
