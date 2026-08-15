<?php

namespace iEXPackages\BestChange\FormulaTags;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class ReserveFilterTagHandler implements FormulaTagHandler
{
    private array $filtered;
    private string $typePosition;

    public function __construct(array $filtered, string $typePosition = 'rate')
    {
        $this->filtered = $filtered;
        $this->typePosition = $typePosition;
    }

    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        [$positionsPart, $conditionPart] = explode('|', $expression) + [null, null];
        [$cond, $minReserve] = explode(':', $conditionPart);

        if ($cond !== 'min_reserve') return '0';

        foreach (explode(',', $positionsPart) as $pos) {
            $index = (int)$pos - 1;
            if (($this->filtered[$index]['reserve'] ?? 0) >= (float)$minReserve) {
                return (string)$this->filtered[$index][$this->typePosition];
            }
        }

        return '0';
    }
}
