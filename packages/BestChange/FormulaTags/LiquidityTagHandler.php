<?php

namespace iEXPackages\BestChange\FormulaTags;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class LiquidityTagHandler implements FormulaTagHandler
{
    private array $filtered;

    public function __construct(array $filtered)
    {
        $this->filtered = $filtered;
    }

    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        $positions = array_map('trim', explode(',', $expression));
        $reserves = [];

        foreach ($positions as $pos) {
            $index = (int)$pos - 1;
            if (isset($this->filtered[$index]['reserve'])) {
                $reserves[] = $this->filtered[$index]['reserve'];
            }
        }

        if (empty($reserves)) return '0';

        return number_format(array_sum($reserves) / count($reserves), $precision, '.', '');
    }
}
