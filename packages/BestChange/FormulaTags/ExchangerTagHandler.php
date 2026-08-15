<?php

namespace iEXPackages\BestChange\FormulaTags;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class ExchangerTagHandler implements FormulaTagHandler
{
    private array $filtered;

    public function __construct(array $filtered)
    {
        $this->filtered = $filtered;
    }

    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        [$position, $field] = array_map('trim', explode('.', $expression, 2) + [1 => null]);
        $positionIndex = ((int)$position) - 1;

        if (!isset($this->filtered[$positionIndex]['exchanger'])) {
            return '0';
        }

        $exchanger = $this->filtered[$positionIndex]['exchanger'];

        // Поддержка вложенных полей, например reviews.positive
        $fields = explode('.', $field);
        $value = $exchanger;

        foreach ($fields as $f) {
            if (!is_array($value) || !isset($value[$f])) {
                return '0';
            }
            $value = $value[$f];
        }

        return is_scalar($value) ? (string)$value : '0';
    }
}
