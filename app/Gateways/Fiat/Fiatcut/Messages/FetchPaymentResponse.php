<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Fiatcut\Messages;

use iEXPackages\Payments\Core\Contracts\FetchPaymentResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class FetchPaymentResponse extends AbstractResponse implements FetchPaymentResponseInterface
{
    /**
     * Транзакция считается найденной и “нашей”, если:
     * - success == 1
     * - data.external_id == task.id
     */
    public function exists(): bool
    {
        $success = $this->dataGet('success');

        if ((int) $success !== 1) {
            return false;
        }

        $externalId = $this->safeString($this->dataGet('data.external_id'));
        $taskId     = $this->getTask() ? (string) $this->getTask()->id : null;

        if ($externalId === null || $taskId === null || $taskId === '') {
            return false;
        }

        // как в старом: int сравнение
        return (int) $externalId === (int) $taskId;
    }

    public function getStatus(): ?string
    {
        return $this->safeString($this->dataGet('data.status'));
    }

    /**
     * Сумма платежа (строкой, чтобы не терять точность).
     */
    public function getAmount(): ?string
    {
        return $this->safeDecimal($this->dataGet('data.amount'));
    }

    public function isSuccessful(): bool
    {
        return $this->isFindPayment() && $this->getStatus() === 'success';
    }

    public function isPending(): bool
    {
        return $this->isFindPayment() && $this->getStatus() === 'pending';
    }

    public function isCancelled(): bool
    {
        return $this->isFindPayment() && in_array($this->getStatus(), ['fail'], true);
    }

    public function getErrorMessage(): ?string
    {
        if (!$this->isFindPayment()) {
            return 'Транзакция не найдена';
        }

        if ($this->isCancelled()) {
            return $this->safeString($this->dataGet('message'))
                ?? $this->safeString($this->dataGet('error'))
                ?? 'Транзакция отменена';
        }

        return null;
    }
}
