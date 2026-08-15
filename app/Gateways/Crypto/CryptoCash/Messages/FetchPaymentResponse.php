<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\CryptoCash\Messages;

use iEXPackages\Payments\Core\Contracts\FetchPaymentResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;
use Illuminate\Support\Str;

final class FetchPaymentResponse extends AbstractResponse implements FetchPaymentResponseInterface
{
    private const STATUSES_SUCCESS = [
        'paid',
        'overpaid',
    ];

    private const STATUSES_PENDING = [
        'new',
        'waiting',
        'underpaid',
        'aml frozen',
        'aml kyc',
    ];

    private const STATUSES_CANCELLED = [
        'canceled',
        'currency mismatch',
    ];

    public function isRegisterPayment(): bool
    {
        return true;
    }

    public function exists(): bool
    {
        $id = $this->safeString($this->dataGet('data.item.id'));
        return $id !== null && $id !== '';
    }

    /**
     * Найдена ли транзакция (существует ли item.id).
     */
    public function isFindPayment(): bool
    {
        $id = $this->safeString($this->dataGet('data.item.id'));
        return $id !== null && $id !== '';
    }

    /**
     * Статус транзакции (в нижнем регистре).
     */
    public function getStatus(): ?string
    {
        $status = $this->safeString($this->dataGet('data.item.status'));
        return $status !== null ? Str::lower($status) : null;
    }

    public function isSuccessful(): bool
    {
        return $this->isFindPayment()
            && in_array($this->getStatus(), self::STATUSES_SUCCESS, true);
    }

    public function isPending(): bool
    {
        return $this->isFindPayment()
            && in_array($this->getStatus(), self::STATUSES_PENDING, true);
    }

    public function isCancelled(): bool
    {
        return $this->isFindPayment()
            && in_array($this->getStatus(), self::STATUSES_CANCELLED, true);
    }

    /**
     * Сумма (строкой, чтобы не терять точность).
     */
    public function getAmount(): ?string
    {
        return $this->safeDecimal($this->dataGet('data.item.amount'));
    }

    public function getCurrency(): ?string
    {
        // ожидаемая валюта по заявке
        $cur = $this->safeString($this->dataGet('data.item.balanceEntries.0.requestedCurrency'))
            ?? $this->safeString($this->dataGet('data.item.requestedCurrency'))
            ?? $this->safeString($this->dataGet('data.item.balanceEntries.0.currency'))
            ?? $this->safeString($this->dataGet('data.item.pair'));

        // если pair типа "USDT/USDT" — аккуратно режем
        if ($cur && str_contains($cur, '/')) {
            $cur = explode('/', $cur, 2)[0] ?? $cur;
        }

        return $cur ? strtoupper($cur) : null;
    }


    /**
     * Хэш транзакции в блокчейне.
     */
    public function getTransactionHash(): ?string
    {
        return $this->safeString($this->dataGet('data.item.hash'));
    }

    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful() || $this->isPending()) {
            return null;
        }

        if (!$this->isFindPayment()) {
            return 'Транзакция не найдена';
        }

        // если API отдаёт message/error
        return $this->safeString($this->dataGet('message'))
            ?? $this->safeString($this->dataGet('error'))
            ?? ('Статус: ' . ($this->getStatus() ?? 'unknown'));
    }

    public function getStatusDescription(): string
    {
        return match ($this->getStatus()) {
            'paid', 'overpaid' => 'Транзакция успешно оплачена',

            'new'        => 'Транзакция создана',
            'waiting'    => 'Ожидание оплаты',
            'underpaid'  => 'Недоплата, ожидание доплаты',
            'aml frozen' => 'AML проверка: заморожено',
            'aml kyc'    => 'AML проверка: требуется KYC',

            'canceled'          => 'Транзакция отменена',
            'currency mismatch' => 'Несовпадение валюты (currency mismatch)',

            default => 'Статус транзакции не определен',
        };
    }
}
