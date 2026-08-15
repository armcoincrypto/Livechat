<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\ObmenkaClubV2\Messages;

use iEXPackages\Payments\Core\Contracts\FiatPayoutResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;
use iEXPackages\Payments\Core\Traits\FiatPayoutTrackingTrait;

final class PayoutResponse extends AbstractResponse implements FiatPayoutResponseInterface
{
    use FiatPayoutTrackingTrait;

    /**
     * Выплата принята системой (создана/зарегистрирована), если:
     * - success === true
     * - есть result.txn
     */
    public function isSuccessful(): bool
    {
        $success = $this->dataGet('success');
        $txn = $this->safeString($this->dataGet('result.txn'));

        return $success === true && $txn !== null;
    }

    /**
     * Уникальный ID выплаты в провайдере.
     */
    public function getWithdrawalId(): ?string
    {
        return $this->safeString($this->queryGet('token'));
    }
    /**
     * Нужно ли дальше отслеживать выплату.
     * Если payout "принят" и успех отложен — да.
     */
    public function isFiatPayoutTrackingRequired(): bool
    {
        return $this->isSuccessful() && $this->isDeferredSuccess();
    }

    /**
     * Отложенный успех:
     * payout принят, но финальный статус будет позже (cron).
     */
    public function isDeferredSuccess(): bool
    {
        return true;
    }

    public function getTrackingMode(): string
    {
        // ты хочешь: "далее работает в cron"
        return 'cron';
    }

    public function getStatus(): ?string
    {
        return $this->isSuccessful() ? 'success' : null;
    }

    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful()) {
            return null;
        }

        return $this->safeString($this->dataGet('message'))
            ?? $this->safeString($this->dataGet('error'))
            ?? 'Ошибка выплаты';
    }
}
