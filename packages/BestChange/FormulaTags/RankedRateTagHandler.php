<?php

namespace iEXPackages\BestChange\FormulaTags;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;


class RankedRateTagHandler implements FormulaTagHandler
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
        [$criteria, $positionsPart] = explode(',', $expression, 2);

        $positions = explode(',', $positionsPart);
        $bestPos = null;
        $maxCriteria = -INF;

        foreach ($positions as $pos) {
            $index = (int)$pos - 1;
            $item = $this->filtered[$index] ?? null;

            if (!$item) continue;

            $currentCriteria = match ($criteria) {
                'positive_reviews' => $item['exchanger']['reviews']['positive'] ?? 0,
                default => 0
            };

            if ($currentCriteria > $maxCriteria) {
                $maxCriteria = $currentCriteria;
                $bestPos = $item[$this->typePosition];
            }
        }

        return $bestPos ? (string)$bestPos : '0';
    }
}
