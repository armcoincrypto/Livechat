<?php
namespace iEXPackages\Courses\FormulaTags\Contracts;

use iEXPackages\Courses\Services\FormulaParserService;

interface FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string;
}
