<?php

declare(strict_types=1);

namespace iEXPackages\Payments;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \iEXPackages\Payments\Core\Contracts\GatewayInterface fromFile(string $alias, string $filename)
 * @method static \iEXPackages\Payments\Core\Contracts\GatewayInterface forMerchant(object $merchant)
 * @method static \iEXPackages\Payments\Core\Config\GatewayConfig        forConfig(string $alias)
 */
final class Payments extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'payments';
    }
}
