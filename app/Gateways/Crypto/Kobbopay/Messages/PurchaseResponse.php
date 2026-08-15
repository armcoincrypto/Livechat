<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Kobbopay\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class PurchaseResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        $receiver  = $this->safeString($this->dataGet('receiver'));
        $trackerId = $this->safeString($this->dataGet('tracker_id'));

        return $receiver !== null && $receiver !== ''
            && $trackerId !== null && $trackerId !== '';
    }

    public function getAccountNumber(): ?string
    {
        return $this->safeString($this->dataGet('receiver'));
    }

    public function getCurrency(): ?string
    {
        return $this->safeString($this->dataGet('token'));
    }

    public function getAccountTag(): ?string
    {
        return $this->safeString($this->dataGet('dest_tag'));
    }

    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('tracker_id'));
    }

    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful()) {
            return null;
        }

        $msg = $this->safeString($this->dataGet('message'))
            ?? $this->safeString($this->dataGet('error'))
            ?? $this->safeString($this->dataGet('error_message'));

        if ($msg !== null && $msg !== '') {
            return $msg;
        }

        $code = $this->dataGet('status');
        if ($code !== null && $code !== '' && is_numeric($code)) {
            return 'Платёжный сервис вернул отказ (код: ' . (string) $code . ').';
        }

        return 'Провайдер не вернул необходимые поля (receiver, tracker_id).';
    }
}
