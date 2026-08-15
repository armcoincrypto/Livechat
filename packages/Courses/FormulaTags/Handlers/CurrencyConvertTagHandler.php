<?php

namespace iEXPackages\Courses\FormulaTags\Handlers;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;
use Illuminate\Support\Facades\Log;

class CurrencyConvertTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        $pairs = explode(',', $expression);

        if (count($pairs) !== 2) {
            return '0';
        }

        [$firstPair, $secondPair] = array_map('trim', $pairs);

        // Получаем значения курсов
        $firstRate = $parser->calculate($firstPair, $precision);
        $secondRate = $parser->calculate($secondPair, $precision);

        if (!is_numeric($firstRate) || !is_numeric($secondRate)) {
            return '0';
        }

        // Выполняем конвертацию
        return bcmul($firstRate, $secondRate, $precision);
    }
}
