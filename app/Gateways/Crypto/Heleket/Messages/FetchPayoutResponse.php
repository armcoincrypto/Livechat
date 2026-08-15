<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Heleket\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class FetchPayoutResponse extends AbstractResponse
{
    public const STATUS_PROCESS     = 'process';
    public const STATUS_CHECK       = 'check';
    public const STATUS_PAID        = 'paid';
    public const STATUS_FAIL        = 'fail';
    public const STATUS_CANCEL      = 'cancel';
    public const STATUS_SYSTEM_FAIL = 'system_fail';

    private const array STATUSES_SUCCESS = [
        self::STATUS_PAID,
    ];

    private const array STATUSES_PENDING = [
        self::STATUS_PROCESS,
        self::STATUS_CHECK,
    ];

    private const array STATUSES_CANCELLED = [
        self::STATUS_FAIL,
        self::STATUS_CANCEL,
        self::STATUS_SYSTEM_FAIL,
    ];

    private const array STATUS_DESCRIPTIONS = [
        self::STATUS_PROCESS     => 'Выплата в процессе',
        self::STATUS_CHECK       => 'Выплата проверяется',
        self::STATUS_PAID        => 'Выплата прошла успешно',
        self::STATUS_FAIL        => 'Выплата не удалась',
        self::STATUS_CANCEL      => 'Выплата отменена',
        self::STATUS_SYSTEM_FAIL => 'Произошла системная ошибка',
    ];

    /**
     * Сигнал "ответ валиден".
     * В Heleket state=0 означает, что запрос обработан корректно.
     */
    private function stateOk(): bool
    {
        return (int) ($this->dataGet('state') ?? 1) === 0
            && $this->safeString($this->dataGet('result.uuid')) !== null;
    }

    /**
     * Heleket: result.status (lowercase).
     */
    public function getStatus(): ?string
    {
        $status = $this->safeString($this->dataGet('result.status'));
        $status = $status !== null ? strtolower($status) : null;

        return $status !== '' ? $status : null;
    }

    private function inStatus(array $set): bool
    {
        $status = $this->getStatus();
        return $status !== null && in_array($status, $set, true);
    }

    public function isSuccessful(): bool
    {
        return $this->stateOk() && $this->inStatus(self::STATUSES_SUCCESS);
    }

    public function isPending(): bool
    {
        return $this->stateOk() && $this->inStatus(self::STATUSES_PENDING);
    }

    public function isCancelled(): bool
    {
        return $this->stateOk() && $this->inStatus(self::STATUSES_CANCELLED);
    }

    public function getStatusDescription(): string
    {
        if (!$this->stateOk()) {
            return 'Не удалось получить статус выплаты';
        }

        $status = $this->getStatus();
        return $status ? (self::STATUS_DESCRIPTIONS[$status] ?? 'Статус не определён') : 'Статус не определён';
    }

    /**
     * txid (может быть null).
     */
    public function getTransactionHash(): ?string
    {
        $txid = $this->safeString($this->dataGet('result.txid'));
        return $txid !== '' ? $txid : null;
    }


    /**
     * Адрес получателя.
     */
    public function getAddress(): ?string
    {
        return $this->safeString($this->dataGet('result.address'));
    }

    /**
     * Внешний ID выплаты у провайдера (uuid).
     */
    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('result.uuid'));
    }

    /**
     * Сумма выплаты в currency.
     */
    public function getAmount(): ?string
    {
        return $this->safeDecimal($this->dataGet('result.amount'));
    }

    /**
     * "amount with fee" — если провайдер отдаёт merchant_amount/commission, можно расширить.
     * Сейчас fallback на amount.
     */
    public function getAmountWithFee(): ?string
    {
        // Если у Heleket есть result.merchant_amount — можно использовать его:
        $merchantAmount = $this->safeDecimal($this->dataGet('result.merchant_amount'));
        if ($merchantAmount !== null && $merchantAmount !== '') {
            return $merchantAmount;
        }

        return $this->getAmount();
    }
}
