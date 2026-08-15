<?php

namespace iEXPackages\BestChange\FormulaTags;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class CorrectTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        [$posTag, $externalTag, $correction] = array_map('trim', explode(',', $expression));

        $posValue = (float) $parser->calculate("$posTag", $precision);

        $externalValue = (float) $parser->calculate("$externalTag", $precision);

        $correction = (float) $correction;

        // Ограничиваем курс позиции с учётом погрешности (коррекции)
        $correctedValue = min($posValue, $externalValue * (1 + $correction));

        return number_format($correctedValue, $precision, '.', '');
    }
}
