<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Fiatcut;

use iEXPackages\Payments\Core\Engine\AbstractGateway;

/**
 * Шлюз: Fiatcut
 * Alias: fiatcut
 * Category: fiat
 *
 * Важно:
 * - Операции берутся из config.php -> operations.
 * - Если включён auto_operations, методы purchase/payout/fetchPayment/fetchPayout
 *   будут доступны автоматически через __call() в AbstractGateway.
 *
 * @method \iEXPackages\Payments\Core\Contracts\RequestInterface purchase(array $parameters = [])
 * @method \iEXPackages\Payments\Core\Contracts\RequestInterface fetchPayment(array $parameters = [])
 */
final class Gateway extends AbstractGateway
{
    protected string $name = 'Fiatcut';
}
