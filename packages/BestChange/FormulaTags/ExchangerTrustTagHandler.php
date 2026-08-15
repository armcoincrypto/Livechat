<?php

namespace iEXPackages\BestChange\FormulaTags;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class ExchangerTrustTagHandler implements FormulaTagHandler
{
    private array $filtered;

    public function __construct(array $filtered)
    {
        $this->filtered = $filtered;
    }

    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        $position = ((int)$expression) - 1;
        if (!isset($this->filtered[$position]['exchanger']['reviews'])) {
            return '0';
        }

        $reviews = $this->filtered[$position]['exchanger']['reviews'];

        $positive = $reviews['positive'] ?? 0;
        $neutral = $reviews['neutral'] ?? 0;
        $claim = $reviews['claim'] ?? 0;

        $total = $positive + $neutral + $claim;

        if ($total === 0) return '0';

        $trust = ($positive / $total) * 100;

        return number_format($trust, 2, '.', '');
    }
}
