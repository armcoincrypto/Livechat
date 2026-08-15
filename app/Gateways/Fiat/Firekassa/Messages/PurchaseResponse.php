<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Firekassa\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

/**
 * Ответ на создание платежа (incoming) для Firekassa.
 *
 * Пример ответа:
 * [
 *   'id' => 518034485,
 *   'payment_id' => '...',
 *   'order_id' => 999,
 *   'account' => '4279....',
 *   'card_number' => '4817....', // реквизит получателя
 *   'payment_url' => 'https://...',
 *   'status' => 'process',
 *   'currency' => 'RUB',
 *   'amount' => '1000.00',
 *   'first_name' => 'роман',
 *   'last_name' => 'богатырев',
 *   'middle_name' => 'владимирович',
 *   'bank' => 'sber',
 *   'payment_error' => null,
 *   ...
 * ]
 */
final class PurchaseResponse extends AbstractResponse
{
    /**
     * Успешно ли создан платёж.
     *
     * Firekassa: считаем успехом, если есть id и payment_id.
     */
    public function isSuccessful(): bool
    {
        $id = $this->dataGet('id');
        $paymentId = $this->safeString($this->dataGet('payment_id'));

        return is_numeric($id) && (int) $id > 0 && $paymentId !== null && $paymentId !== '';
    }

    /**
     * Реквизит для оплаты:
     * - приоритет: card_number (карта получателя)
     * - fallback: account
     */
    public function getAccountNumber(): ?string
    {
        $card = $this->safeString($this->dataGet('card_number'));
        return ($card !== null && $card !== '') ? $card : null;
    }

    /**
     * Название банка/метода (если надо в UI).
     * У тебя в ответе это "bank" (пример: sber).
     */
    public function getBankName(): ?string
    {
        $bank = $this->safeString($this->dataGet('bank'));
        return ($bank !== null && $bank !== '') ? $bank : null;
    }

    /**
     * ФИО получателя (если отдаётся).
     *
     * Склеиваем:
     * last_name first_name middle_name
     */
    public function getAccountTag(): ?string
    {
        $last   = trim((string) ($this->safeString($this->dataGet('last_name')) ?? ''));
        $first  = trim((string) ($this->safeString($this->dataGet('first_name')) ?? ''));
        $middle = trim((string) ($this->safeString($this->dataGet('middle_name')) ?? ''));

        $fio = trim(implode(' ', array_filter([$last, $first, $middle], static fn ($v) => $v !== '')));

        return $fio !== '' ? $fio : null;
    }

    /**
     * Валюта платежа (например RUB).
     */
    public function getCurrency(): ?string
    {
        $currency = $this->safeString($this->dataGet('currency'));
        return ($currency !== null && $currency !== '') ? $currency : null;
    }

    /**
     * Сумма платежа (строкой).
     */
    public function getAmount(): ?string
    {
        return $this->safeDecimal($this->dataGet('amount'));
    }

    /**
     * Внешний идентификатор операции в системе провайдера.
     *
     * В Firekassa удобнее хранить payment_id (uuid),
     * потому что он чаще используется для трекинга.
     */
    public function getExternalId(): ?string
    {
        $paymentId = $this->safeString($this->dataGet('id'));
        return ($paymentId !== null && $paymentId !== '') ? $paymentId : null;
    }

    /**
     * Статус Firekassa (process/paid/fail/...).
     */
    public function getStatus(): ?string
    {
        $status = $this->safeString($this->dataGet('status'));
        return ($status !== null && $status !== '') ? $status : null;
    }

    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful()) {
            return null;
        }

        $err = $this->safeString($this->dataGet('payment_error'))
            ?? $this->safeString($this->dataGet('message'));

        return ($err !== null && trim($err) !== '')
            ? $err
            : 'Не удалось создать платёж в Firekassa';
    }
}
