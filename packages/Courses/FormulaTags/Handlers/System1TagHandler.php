<?php

namespace iEXPackages\Courses\FormulaTags\Handlers;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class System1TagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        $expression = html_entity_decode($expression);

        // Обработка тернарного оператора
        if (preg_match('/^(.*?)(<=|>=|==|!=|<|>)(.*?)\?(.*?):(.*)$/', $expression, $matches)) {
            [$full, $left, $operator, $right, $trueExpr, $falseExpr] = array_map('trim', $matches);

            $leftVal = $parser->calculate($left, $precision);
            $rightVal = $parser->calculate($right, $precision);

            $conditionResult = match ($operator) {
                '<=' => bccomp($leftVal, $rightVal, $precision) <= 0,
                '>=' => bccomp($leftVal, $rightVal, $precision) >= 0,
                '==' => bccomp($leftVal, $rightVal, $precision) == 0,
                '!=' => bccomp($leftVal, $rightVal, $precision) != 0,
                '<'  => bccomp($leftVal, $rightVal, $precision) < 0,
                '>'  => bccomp($leftVal, $rightVal, $precision) > 0,
                default => false,
            };

            return $parser->calculate($conditionResult ? $trueExpr : $falseExpr, $precision);
        }

        // Если тернарного оператора нет — обычное вычисление
        return $parser->calculate($expression, $precision);
    }
}
