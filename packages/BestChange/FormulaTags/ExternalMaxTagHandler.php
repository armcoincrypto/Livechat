<?php

namespace iEXPackages\BestChange\FormulaTags;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class ExternalMaxTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        [$firstTag, $secondTag] = explode(',', $expression, 2);

        $firstValue = (float)$parser->calculate($this->prepareTag($firstTag), $precision);
        $secondValue = (float)$parser->calculate($this->prepareTag($secondTag), $precision);

        return number_format(max($firstValue, $secondValue), $precision, '.', '');
    }

    private function prepareTag(string $tag): string
    {
        return str_starts_with(trim($tag), '[') ? trim($tag) : "[{$tag}]";
    }
}
