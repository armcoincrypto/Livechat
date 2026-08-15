<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Rapira;

use iEXPackages\Payments\Core\Engine\AbstractGateway;


/**
 * @method \iEXPackages\Payments\Core\Contracts\RequestInterface purchase(array $parameters = [])
 * @method \iEXPackages\Payments\Core\Contracts\RequestInterface fetchPayment(array $parameters = [])
 * @method \iEXPackages\Payments\Core\Contracts\RequestInterface payout(array $parameters = [])
 * @method \iEXPackages\Payments\Core\Contracts\RequestInterface fetchPayout(array $parameters = [])
 */
final class Gateway extends AbstractGateway
{
    protected string $name = 'Rapira';
}
