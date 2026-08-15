<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\AlfabitPay\Messages;

use iEXPackages\Payments\Core\Contracts\PollingResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class PayoutResponse extends AbstractResponse implements PollingResponseInterface
{
    /**
     * Успешна ли выплата.
     *
     * Для AlfabitPay выплата считается созданной,
     * если API вернул data.uid.
     */
    public function isSuccessful(): bool
    {
        $uid = $this->safeString($this->dataGet('data.uid'));
        return $uid !== null && $uid !== '';
    }

    /**
     * Унифицированный статус.
     */
    public function getStatus(): ?string
    {
        return $this->isSuccessful() ? 'success' : null;
    }

    /**
     * Требуется ли polling (проверка статуса).
     */
    public function isPollingRequired(): bool
    {
        return true;
    }

    /**
     * Тип polling.
     *
     * Можно использовать для маршрутизации:
     * payout / withdrawal / tx
     */
    public function getPollingType(): ?string
    {
        return 'payout';
    }

    /**
     * Ключ для polling-запроса.
     */
    public function getPollingKey(): ?string
    {
        return $this->getExternalId();
    }

    /**
     * Внешний идентификатор выплаты (UID).
     */
    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('data.uid'));
    }

    /**
     * Сообщение об ошибке (если неуспех).
     */
    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful()) {
            return null;
        }

        return $this->safeString($this->dataGet('message'))
            ?? $this->safeString($this->dataGet('error'))
            ?? $this->safeString($this->dataGet('error_message'))
            ?? 'Не удалось создать выплату';
    }
}
