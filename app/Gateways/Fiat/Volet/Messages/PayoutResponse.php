<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Volet\Messages;

use iEXPackages\Payments\Core\Contracts\FiatPayoutResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;
use iEXPackages\Payments\Core\Traits\FiatPayoutTrackingTrait;

final class PayoutResponse extends AbstractResponse
{
    use FiatPayoutTrackingTrait;

    /**
     * Выплата успешно СОЗДАНА (не завершена),
     * если status === 'success'.
     */
    public function isSuccessful(): bool
    {
        return $this->safeString($this->dataGet('status')) === 'success';
    }

    /**
     * Уникальный ID выплаты в системе провайдера.
     *
     * Старое поведение:
     *   txn || tx_id
     */
    public function getWithdrawalId(): ?string
    {
        return $this->safeString($this->dataGet('return'));
    }

    /**
     * Текст ошибки, если payout не создан.
     */
    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful()) {
            return null;
        }

        return
            $this->safeString($this->dataGet('message'))
            ?? $this->safeString($this->dataGet('error'))
            ?? 'Ошибка выплаты';
    }
}
