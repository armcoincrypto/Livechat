<?php

namespace iEXPackages\BestChange\FormulaTags;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;


class WeightedAvgTagHandler implements FormulaTagHandler
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
        $positions = array_map('trim', explode(',', $expression));
        $sumProduct = 0;
        $sumReserve = 0;

        foreach ($positions as $pos) {
            $index = (int)$pos - 1;
            $rate = (float)($this->filtered[$index][$this->typePosition] ?? 0);
            $reserve = (float)($this->filtered[$index]['reserve'] ?? 0);

            $sumProduct += $rate * $reserve;
            $sumReserve += $reserve;
        }

        return $sumReserve ? number_format($sumProduct / $sumReserve, $precision, '.', '') : '0';
    }
}
