<?php

namespace iEXPackages\BestChange\FormulaTags;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class ExchangerRatingTagHandler implements FormulaTagHandler
{
    private array $filtered;

    public function __construct(array $filtered)
    {
        $this->filtered = $filtered;
    }

    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        $position = ((int)$expression) - 1;

        if (!isset($this->filtered[$position]['exchanger']['reviews']['positive'])) {
            return '0';
        }

        // Берём количество положительных отзывов как рейтинг
        $rating = (int)$this->filtered[$position]['exchanger']['reviews']['positive'];

        return (string)$rating;
    }
}
