<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Firekassa\Messages;

use iEXPackages\Payments\Core\Contracts\FetchPaymentResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class FetchPaymentResponse extends AbstractResponse implements FetchPaymentResponseInterface
{
    /**
     * Firekassa: запись считается найденной, если есть id/payment_id.
     * "Наша" — если order_id совпадает с Task->id (или query.order_id).
     */
    public function exists(): bool
    {
        $id = $this->dataGet('id');
        $paymentId = $this->safeString($this->dataGet('payment_id'));

        // 1) вообще есть запись
        $exists = (is_numeric($id) && (int)$id > 0) || ($paymentId !== null && $paymentId !== '');
        if (!$exists) {
            return false;
        }

        // 2) проверяем "нашу" order_id
        $orderId = $this->safeString($this->dataGet('order_id'));
        if ($orderId === null || $orderId === '') {
            return false;
        }

        // приоритет: Task->id, fallback: query.order_id
        $queryOrderId = $this->safeString($this->queryGet('order_id'));

        $expected = $this->getTransactionId() ?: $queryOrderId;

        if ($expected === null || $expected === '') {
            return false;
        }

        return (int)$orderId === (int)$expected;
    }


    public function getStatus(): ?string
    {
        $status = $this->safeString($this->dataGet('status'));
        return $status !== null ? strtolower($status) : null;
    }

    /**
     * Сумма платежа (строкой, чтобы не терять точность).
     */
    public function getAmount(): ?string
    {
        // Firekassa отдаёт amount как строку
        return $this->safeDecimal($this->dataGet('amount'));
    }

    public function isCancelled(): bool
    {
        // как ты просил
        return $this->exists() && in_array($this->getStatus(), ['error', 'cancel', 'expired'], true);
    }

    public function isPending(): bool
    {
        // как ты просил
        return $this->exists() && in_array($this->getStatus(), ['process', 'partially-paid'], true);
    }

    public function isSuccessful(): bool
    {
        // как ты просил
        return $this->exists() && in_array($this->getStatus(), ['paid', 'overpaid'], true);
    }

    public function getExternalId(): ?string
    {
        // удобнее всего трекать по payment_id (uuid)
        $paymentId = $this->safeString($this->dataGet('id'));
        return ($paymentId !== null && $paymentId !== '') ? $paymentId : null;
    }

    public function getCurrency(): ?string
    {
        return $this->safeString($this->dataGet('currency'));
    }


    public function getErrorMessage(): ?string
    {
        if (!$this->exists()) {
            return 'Транзакция не найдена';
        }

        if ($this->isCancelled()) {
            return $this->safeString($this->dataGet('payment_error'))
                ?? $this->safeString($this->dataGet('message'))
                ?? $this->safeString($this->dataGet('error'))
                ?? ('Статус: ' . ($this->getStatus() ?? 'unknown'));
        }

        return null;
    }

    public function getStatusDescription(): string
    {
        return match ($this->getStatus()) {
            'process'         => 'Платёж в процессе обработки',
            'partially-paid'  => 'Платёж частично оплачен',
            'paid'            => 'Платёж успешно выполнен',
            'overpaid'        => 'Платёж выполнен с переплатой',
            'expired'         => 'Платёж просрочен',
            'cancel'          => 'Платёж отменён',
            'error'           => 'Платёж завершился ошибкой',
            default           => 'Статус не определён',
        };
    }
}
