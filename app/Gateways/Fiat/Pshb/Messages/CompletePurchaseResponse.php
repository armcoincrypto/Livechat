<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Pshb\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

/**
 * CompletePurchaseResponse (PSHB)
 *
 * PSHB присылает поле operation.status.
 * Возможные статусы:
 *   - Success
 *   - Decline
 *   - Processing
 *   - Awaiting Redirect
 *   - Awaiting Confirmation
 *   - External error
 *   - Internal error
 *   - Canceled
 */
final class CompletePurchaseResponse extends AbstractResponse
{
    /**
     * Финальный успех.
     * Success => платёж подтверждён
     */
    public function isSuccessful(): bool
    {
        return $this->getOperationStatus() === 'Success';
    }

    /**
     * Финальная ошибка/отмена.
     * Decline, Canceled, External error, Internal error => платёж завершён с ошибкой/отменён
     */
    public function isCancelled(): bool
    {
        return in_array($this->getOperationStatus(), ['Decline', 'Canceled', 'External error', 'Internal error'], true);
    }

    /**
     * Ожидание обработки.
     * Processing, Awaiting Redirect, Awaiting Confirmation => платёж в ожидании
     */
    public function isPending(): bool
    {
        return in_array($this->getOperationStatus(), ['Processing', 'Awaiting Redirect', 'Awaiting Confirmation'], true);
    }

    /**
     * Внешний ID операции у провайдера.
     */
    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('transaction_id'))
            ?? $this->safeString($this->dataGet('operation.id'));
    }

    /**
     * ID заявки в нашей системе.
     * В доке: order_id — "ID платежа или клиента в Вашей системе"
     */
    public function getOrderId(): ?string
    {
        return $this->safeString($this->dataGet('order_id'));
    }

    /**
     * Сумма (PSHB присылает в минорных единицах).
     * Пример: 125000 => 1250.00
     */
    public function getAmount(): ?string
    {
        $raw = $this->dataGet('operation.amount');

        if (is_int($raw)) {
            return number_format($raw / 100, 2, '.', '');
        }

        if (is_string($raw) && $raw !== '' && is_numeric($raw)) {
            return number_format(((int) $raw) / 100, 2, '.', '');
        }

        return null;
    }

    /**
     * Валюта суммы платежа.
     */
    public function getCurrency(): ?string
    {
        return $this->safeString($this->dataGet('operation.currency'));
    }

    /**
     * Текст ошибки (если есть).
     */
    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful() || $this->isPending()) {
            return null;
        }

        return $this->safeString($this->dataGet('operation.error_message'))
            ?? $this->safeString($this->dataGet('provider_details.message'))
            ?? $this->getOperationStatus()
            ?? 'PSHB: платёж завершён с ошибкой';
    }

    /**
     * Получить статус операции (PSHB).
     */
    private function getOperationStatus(): ?string
    {
        $status = $this->safeString($this->dataGet('operation.status'));
        return $status !== null ? trim($status) : null;
    }
}
