<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\CryptoCash\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;
use Illuminate\Support\Str;

final class FetchPayoutResponse extends AbstractResponse
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

    public function getStatus(): ?string
    {
        $status = $this->safeString($this->dataGet('data.item.status'));
        return $status !== null ? Str::lower($status) : null;
    }

    /**
     * Успех = Paid/Overpaid.
     */
    public function isSuccessful(): bool
    {
        return $this->isFindPayout()
            && in_array($this->getStatus(), self::STATUSES_SUCCESS, true);
    }

    /**
     * В ожидании.
     */
    public function isPending(): bool
    {
        return $this->isFindPayout()
            && in_array($this->getStatus(), self::STATUSES_PENDING, true);
    }

    /**
     * Отменено/ошибка.
     */
    public function isCancelled(): bool
    {
        return $this->isFindPayout()
            && in_array($this->getStatus(), self::STATUSES_CANCELLED, true);
    }

    /**
     * Транзакция найдена (code=200 и есть data.item.id).
     */
    public function isFindPayout(): bool
    {
        $code = $this->dataGet('code');
        if (!is_numeric($code) || (int) $code !== 200) {
            return false;
        }

        $id = $this->safeString($this->dataGet('data.item.id'));
        return $id !== null && $id !== '';
    }


    /**
     * tx_hash / номер транзакции.
     */
    public function getTransactionHash(): ?string
    {
        $hash = $this->safeString($this->dataGet('data.item.hash'));
        if ($hash !== null && $hash !== '') {
            return $hash;
        }

        $entries = $this->dataGet('data.item.balanceEntries');

        if (is_array($entries)) {
            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $h = $entry['hash'] ?? null;
                if (is_string($h)) {
                    $h = trim($h);
                    if ($h !== '') {
                        return $h;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Внешний ID выплаты в системе шлюза.
     */
    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('data.item.externalId'));
    }

    /**
     * Сумма выплаты.
     */
    public function getAmount(): ?string
    {
        return $this->safeDecimal($this->dataGet('data.item.amount'));
    }

    public function getAmountWithFee(): ?string
    {
        // если хочешь: amount + commission, можно считать на сервисном уровне
        return $this->getAmount();
    }

    public function getStatusDescription(): string
    {
        return match ($this->getStatus()) {
            'paid', 'overpaid' => 'Выплата успешно выполнена',

            'new'        => 'Транзакция создана',
            'waiting'    => 'Ожидание обработки',
            'underpaid'  => 'Недостаточно средств (underpaid)',
            'processing' => 'В обработке',
            'sending'    => 'Отправляется',

            'canceled'          => 'Выплата отменена',
            'currency mismatch' => 'Несовпадение валюты',
            'failed'            => 'Ошибка выплаты',

            default => 'Статус выплаты не определен',
        };
    }
}
