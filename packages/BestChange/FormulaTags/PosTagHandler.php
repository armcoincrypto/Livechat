<?php

namespace iEXPackages\BestChange\FormulaTags;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class PosTagHandler implements FormulaTagHandler
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
        // Разделим номер позиции и выражение после запятой
        [$position, $operation] = array_map('trim', explode(',', $expression, 2) + [1 => null]);

        $position = (int)$position;

        // Если позиция некорректна или не найдена, возвращаем 0
        if ($position <= 0 || empty($this->filtered[$position - 1][$this->typePosition])) {
            return '0';
        }

        $baseValue = (string) $this->filtered[$position - 1][$this->typePosition];

        if ($operation === null) {
            return $baseValue;
        }

        $operation = trim($operation);

        // Обрабатываем процентные выражения
        if (str_contains($operation, '%')) {
            // Например, "-1%" или "+1%"
            $percentValue = (float)str_replace('%', '', $operation);
            $multiplier = bcdiv($percentValue, '100', $precision);
            $multiplier = bcadd('1', $multiplier, $precision);

            return bcmul($baseValue, $multiplier, $precision);
        }

        // Обрабатываем обычные арифметические выражения (+, -, *, /)
        if (preg_match('/^([+\-*\/])\s*(\d+(\.\d+)?)$/', $operation, $matches)) {
            [$fullMatch, $operator, $operand] = $matches;

            return match ($operator) {
                '+' => bcadd($baseValue, $operand, $precision),
                '-' => bcsub($baseValue, $operand, $precision),
                '*' => bcmul($baseValue, $operand, $precision),
                '/' => bccomp($operand, '0', $precision) === 0 ? '0' : bcdiv($baseValue, $operand, $precision),
                default => '0',
            };
        }

        // Если выражение не распознано, возвращаем исходное значение
        return $baseValue;
    }
}
