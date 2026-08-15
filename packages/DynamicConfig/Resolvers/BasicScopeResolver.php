<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Resolvers;

use iEXPackages\DynamicConfig\Contracts\ScopeResolverInterface;
use iEXPackages\DynamicConfig\ValueObjects\Scope;

/**
 * Базовый резолвер scope для DynamicConfig.
 *
 * Сейчас:
 *  - глобальный scope = Scope::global()
 *  - текущий scope = global
 *
 * В реальном проекте сюда можно привязать:
 *  - текущую лицензию (license),
 *  - текущий обменник (exchange),
 *  - текущего пользователя (user),
 *  - другие уровни в цепочке scope.
 */
final class BasicScopeResolver implements ScopeResolverInterface
{
    /**
     * Глобальный scope (корень дерева scope).
     */
    public function globalScope(): Scope
    {
        return Scope::global();
    }

    /**
     * Текущий scope.
     *
     * В текущей базовой реализации возвращает globalScope().
     * Можно расширить, чтобы возвращать, например, scope текущего пользователя:
     *
     *  - global
     *  - license
     *  - exchange
     *  - user
     */
    public function currentScope(): Scope
    {
        return $this->globalScope();
    }
}
