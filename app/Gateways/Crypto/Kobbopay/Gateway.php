<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Kobbopay;

use iEXPackages\Payments\Core\Engine\AbstractGateway;

/**
 * Шлюз: Kobbopay
 * Alias: kobbopay
 * Category: crypto
 *
 * @method \iEXPackages\Payments\Core\Contracts\RequestInterface purchase(array $parameters = [])
 * @method \iEXPackages\Payments\Core\Contracts\RequestInterface fetchPayment(array $parameters = [])
 */
final class Gateway extends AbstractGateway
{
    protected string $name = 'Kobbopay / Pay.kobbex';
}
