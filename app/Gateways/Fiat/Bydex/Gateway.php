<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Bydex;

use iEXPackages\Payments\Core\Engine\AbstractGateway;

/**
 * Шлюз: Bydex
 * Alias: bydex
 * Category: fiat
 *
 * Важно:
 * - Операции берутся из config.php -> operations.
 * - Если включён auto_operations, методы purchase/payout/fetchPayment/fetchPayout
 *   будут доступны автоматически через __call() в AbstractGateway.
 *
 * @method \iEXPackages\Payments\Core\Contracts\RequestInterface payout(array $parameters = [])
 * @method \iEXPackages\Payments\Core\Contracts\RequestInterface fetchPayout(array $parameters = [])
 */
final class Gateway extends AbstractGateway
{
    protected string $name = 'Bydex';
}
