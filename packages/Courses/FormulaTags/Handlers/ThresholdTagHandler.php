<?php

namespace iEXPackages\Courses\FormulaTags\Handlers;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class ThresholdTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        $parts = explode(',', $expression);

        // Первый элемент — переменная, остальные — пороги и значения
        $variable = array_shift($parts);
        $currentValue = $parser->calculate(trim($variable), $precision);

        $thresholds = [];

        foreach ($parts as $part) {
            [$limit, $value] = explode(':', $part);
            $thresholds[(float)trim($limit)] = trim($value);
        }

        // Сортируем по возрастанию порогов
        ksort($thresholds);

        foreach ($thresholds as $limit => $value) {
            if (bccomp($currentValue, (string)$limit, $precision) <= 0) {
                return $parser->calculate($value, $precision);
            }
        }

        // Если значение больше последнего порога, вернём последнее значение
        return $parser->calculate(end($thresholds), $precision);
    }
}
