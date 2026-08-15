<?php

declare(strict_types=1);

namespace iEXPackages\BinInspector;

use iEXPackages\BinInspector\Contracts\BinDriverInterface;
use Illuminate\Support\Facades\Facade;

/**
 * Фасад для удобного доступа к BinInspector.
 *
 * Примеры:
 *  BinInspectorFacade::driver('binlist')->setCardNumber('4111...')->getData();
 *  BinInspectorFacade::inspect('4111 1111 1111 1111', 'mrbin');
 *
 * @method static BinDriverInterface      driver(string $name = 'binlist')
 * @method static BinInspectorResult|null inspect(string $cardNumber, ?string $driver = null)
 */
final class BinInspectorFacade extends Facade
{
    /**
     * Имя сервиса в контейнере Laravel.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'bin-inspector';
    }
}
