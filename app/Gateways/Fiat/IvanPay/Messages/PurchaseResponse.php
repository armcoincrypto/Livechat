<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\IvanPay\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class PurchaseResponse extends AbstractResponse
{
    /**
     * Успешно ли создан платёж.
     *
     * У IvanPay "успех" выглядит так:
     * - status == 200
     * - payment.id существует
     */
    public function isSuccessful(): bool
    {
        $status = $this->dataGet('status');

        if (!is_numeric($status) || (int) $status !== 200) {
            return false;
        }

        $id = $this->dataGet('payment.id');

        return is_numeric($id) && (int) $id > 0;
    }

    /**
     * Основные реквизиты для оплаты.
     *
     * card → card_receiver.card_number
     * sbp  → card_receiver.phone_number
     */
    public function getAccountNumber(): ?string
    {
        $card = $this->safeString($this->dataGet('payment.card_receiver.card_number'));
        if ($card !== null && $card !== '') {
            return $card;
        }

        return $this->safeString($this->dataGet('payment.card_receiver.phone_number'));
    }

    /**
     * ФИО получателя (если есть).
     */
    public function getAccountTag(): ?string
    {
        return $this->safeString($this->dataGet('payment.card_receiver.card_holder'));
    }

    /**
     * Название банка получателя (если есть).
     */
    public function getBankName(): ?string
    {
        return $this->safeString($this->dataGet('payment.card_receiver.currency_name'));
    }

    /**
     * Валюта/код направления (как отдаёт IvanPay).
     */
    public function getCurrency(): ?string
    {
        return $this->safeString($this->dataGet('payment.currency'));
    }

    /**
     * Внешний ID операции в системе IvanPay.
     * Это payment.id.
     */
    public function getExternalId(): ?string
    {
        $id = $this->dataGet('payment.id');

        return is_numeric($id) ? (string) $id : $this->safeString($id);
    }

    /**
     * Сумма (строкой, чтобы не терять точность).
     */
    public function getAmount(): ?string
    {
        return $this->safeDecimal($this->dataGet('payment.amount'));
    }

    /**
     * Статус в терминах IvanPay (in_progress / completed / fail / ...).
     */
    public function getStatus(): ?string
    {
        return $this->safeString($this->dataGet('payment.status'));
    }

    /**
     * Текст ошибки (если операция неуспешна).
     */
    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful()) {
            return null;
        }

        // стандартные поля ошибки IvanPay
        $msg = $this->safeString($this->dataGet('error'))
            ?? $this->safeString($this->dataGet('message'));

        if ($msg !== null && $msg !== '') {
            return $msg;
        }

        $code = $this->dataGet('error_code');
        if (is_numeric($code)) {
            return 'Ошибка IvanPay. Код: ' . (string) $code;
        }

        // fallback
        $status = $this->dataGet('status');
        return is_numeric($status)
            ? ('Ошибка IvanPay. HTTP status: ' . (string) $status)
            : 'Ошибка IvanPay. Не удалось определить причину.';
    }
}
