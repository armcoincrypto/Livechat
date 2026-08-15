<?php

namespace iEXPackages\Courses\FormulaTags\Handlers;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

class IfTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        $expression = html_entity_decode($expression);

        $pattern = '/\[elseif\]|\[else\]/';
        $parts = preg_split($pattern, $expression);
        preg_match_all($pattern, $expression, $tags);

        $conditions = [];
        $results = [];

        foreach ($parts as $index => $part) {
            $part = trim($part);
            if ($index == 0 && preg_match('/^(.*?)\s+(.*)$/', $part, $matches)) {
                $conditions[] = $matches[1];
                $results[] = $matches[2];
            } elseif (isset($tags[0][$index - 1]) && $tags[0][$index - 1] === '[else]') {
                $conditions[] = null;
                $results[] = $part;
            } elseif (preg_match('/^(.*?)\s+(.*)$/', $part, $matches)) {
                $conditions[] = $matches[1];
                $results[] = $matches[2];
            }
        }

        foreach ($conditions as $i => $condition) {
            if ($condition === null || $parser->evaluateCondition($condition, $precision)) {
                return $parser->calculate($results[$i], $precision);
            }
        }

        return '0';
    }
}
