<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Contracts;

use iEXPackages\DynamicConfig\ValueObjects\Scope;

/**
 * Определяет, какой scope считать текущим (global / license / exchange / user).
 */
interface ScopeResolverInterface
{
    /**
     * Глобальный scope (общесистемные настройки).
     */
    public function globalScope(): Scope;

    /**
     * Текущий scope по контексту (можно начинать с global).
     */
    public function currentScope(): Scope;
}
