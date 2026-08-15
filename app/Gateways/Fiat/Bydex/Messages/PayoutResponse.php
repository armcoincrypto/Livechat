<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Bydex\Messages;

use iEXPackages\Payments\Core\Contracts\FiatPayoutResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;
use iEXPackages\Payments\Core\Traits\FiatPayoutTrackingTrait;

final class PayoutResponse extends AbstractResponse implements FiatPayoutResponseInterface
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

    public function isFiatPayoutTrackingRequired(): bool
    {
        // payout создан → дальше ждём статуса
        return $this->isSuccessful();
    }

    /**
     * Уникальный ID выплаты в системе провайдера.
     *
     * Старое поведение:
     *   txn || tx_id
     */
    public function getWithdrawalId(): ?string
    {
        return
            $this->safeString($this->dataGet('txn'))
            ?? $this->safeString($this->dataGet('tx_id'))
            ?? $this->safeString($this->dataGet('withdrawal_id'));
    }

    /**
     * Как отслеживаем выплату.
     */
    public function getTrackingMode(): string
    {
        // Bydex / BestMerchant → cron polling
        return 'cron';
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
