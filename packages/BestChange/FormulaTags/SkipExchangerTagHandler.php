<?php

namespace iEXPackages\BestChange\FormulaTags;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class SkipExchangerTagHandler implements FormulaTagHandler
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
        [$positionsPart, $skipNamesPart] = explode('|', $expression) + [null, null];

        $positions = array_map('trim', explode(',', $positionsPart));
        $skipExchangers = $skipNamesPart ? array_map('trim', explode(',', $skipNamesPart)) : [];

        foreach ($positions as $pos) {
            $index = (int)$pos - 1;
            if (!isset($this->filtered[$index])) {
                continue;
            }

            $item = $this->filtered[$index];
            $exchangerName = $item['exchanger']['name'] ?? '';

            // Пропускаем, если обменник в списке исключений
            if (in_array($exchangerName, $skipExchangers)) {
                continue;
            }

            // Возвращаем курс первой подходящей позиции
            return (string)($item[$this->typePosition] ?? '0');
        }

        return '0';
    }
}
