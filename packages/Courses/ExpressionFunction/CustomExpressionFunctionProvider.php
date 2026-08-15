<?php

namespace iEXPackages\Courses\ExpressionFunction;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

class CustomExpressionFunctionProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions(): array
    {
        return [
            new ExpressionFunction('IF',
                fn($condition, $true, $false) => sprintf('(%s ? %s : %s)', $condition, $true, $false),
                fn(array $variables, $condition, $true, $false) => $condition ? $true : $false
            ),
            new ExpressionFunction('MIN',
                fn(...$args) => 'min('.implode(',', $args).')',
                fn(array $variables, ...$args) => min($args)
            ),
            new ExpressionFunction('MAX',
                fn(...$args) => 'max('.implode(',', $args).')',
                fn(array $variables, ...$args) => max($args)
            ),
            new ExpressionFunction('AVG',
                fn(...$args) => 'array_sum(['.implode(',', $args).']) / count(['.implode(',', $args).'])',
                fn(array $variables, ...$args) => array_sum($args) / count($args)
            ),
        ];
    }
}
