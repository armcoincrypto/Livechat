<?php
namespace iEXPackages\ExchangerClient\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static build()
 * @method static buildExchange()
 */
class StartServiceFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'exchanger-client.start';
    }
}
