<?php

namespace iEXPackages\AMLPlugin\Facades;

use iEXPackages\AMLPlugin\AMLManager;
use Illuminate\Support\Facades\Facade;

/**
 * Class AMLFacade
 *
 * @method static \iEXPackages\AMLPlugin\AMLManager driver(string $driverName, \App\Models\AMLService|null $service = null)
 * @method static \iEXPackages\AMLPlugin\AMLManager useCache(bool $useCache = true, int $ttl = 3600)
 * @method static \iEXPackages\AMLPlugin\Contracts\AMLResponseInterface checkTransaction(array $params)
 * @method static \iEXPackages\AMLPlugin\Contracts\AMLResponseInterface checkAddress(array $params)
 */
class AMLFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AMLManager::class;
    }
}
