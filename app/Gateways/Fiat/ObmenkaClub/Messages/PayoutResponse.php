<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\ObmenkaClub\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class PayoutResponse extends AbstractResponse
{
    /**
     * Выплата успешно СОЗДАНА (не завершена),
     * если status === 'success'.
     */
    public function isSuccessful(): bool
    {
        return true;
    }
}
