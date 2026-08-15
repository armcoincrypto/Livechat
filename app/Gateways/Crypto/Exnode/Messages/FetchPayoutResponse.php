<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Exnode\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

/**
 * Ответ Exnode на fetchPayout (получение статуса выплаты).
 *
 * API /api/transaction/get возвращает, как правило:
 * [
 *   'status' => 'ok',
 *   'transaction' => [
 *      'status' => 'ACCEPTED|SUCCESS|ERROR',
 *      'hash'   => '...',
 *      'receiver' => '...',
 *      'token' => 'USDTTRC',
 *      'amount' => 1000,
 *      'tracker_id' => '...',
 *      ...
 *   ]
 * ]
 *
 * Статусы Exnode:
 * - ACCEPTED: Final=false (в процессе)
 * - SUCCESS : Final=true  (успешно)
 * - ERROR   : Final=true  (ошибка/отмена)
 */
final class FetchPayoutResponse extends AbstractResponse
{
    public const STATUS_ACCEPTED = 'ACCEPTED';
    public const STATUS_SUCCESS  = 'SUCCESS';
    public const STATUS_ERROR    = 'ERROR';

    private const array STATUSES_SUCCESS = [
        self::STATUS_SUCCESS,
    ];

    private const array STATUSES_PENDING = [
        self::STATUS_ACCEPTED,
    ];

    private const array STATUSES_CANCELLED = [
        self::STATUS_ERROR,
    ];

    private const array STATUS_DESCRIPTIONS = [
        self::STATUS_ACCEPTED => 'Выплата в процессе (обработка транзакции в блокчейне)',
        self::STATUS_SUCCESS  => 'Выплата успешно выполнена',
        self::STATUS_ERROR    => 'Выплата отменена (ошибка сети или вручную)',
    ];

    /**
     * Ответ валиден, если есть transaction и tracker_id (или хотя бы статус).
     */
    private function exists(): bool
    {
        $tx = $this->dataGet('transaction');
        if (!is_array($tx) || $tx === []) {
            return false;
        }

        // tracker_id обычно присутствует
        $tracker = $this->safeString($this->dataGet('transaction.tracker_id'));
        $status  = $this->safeString($this->dataGet('transaction.status'));

        return ($tracker !== null && $tracker !== '') || ($status !== null && $status !== '');
    }

    /**
     * Статус выплаты (верхний регистр).
     */
    public function getStatus(): ?string
    {
        $status = $this->safeString($this->dataGet('transaction.status'));
        $status = $status !== null ? strtoupper($status) : null;

        return $status !== '' ? $status : null;
    }

    private function inStatus(array $set): bool
    {
        $status = $this->getStatus();
        return $status !== null && in_array($status, $set, true);
    }

    public function isSuccessful(): bool
    {
        return $this->exists() && $this->inStatus(self::STATUSES_SUCCESS);
    }

    public function isPending(): bool
    {
        return $this->exists() && $this->inStatus(self::STATUSES_PENDING);
    }

    public function isCancelled(): bool
    {
        return $this->exists() && $this->inStatus(self::STATUSES_CANCELLED);
    }

    public function getStatusDescription(): string
    {
        if (!$this->exists()) {
            return 'Не удалось получить статус выплаты';
        }

        $status = $this->getStatus();
        return $status ? (self::STATUS_DESCRIPTIONS[$status] ?? 'Статус не определён') : 'Статус не определён';
    }

    /**
     * Hash транзакции в блокчейне.
     * В Exnode это transaction.hash.
     */
    public function getTransactionHash(): ?string
    {
        $hash = $this->safeString($this->dataGet('transaction.hash'));
        return $hash !== '' ? $hash : null;
    }

    /**
     * Адрес получателя.
     */
    public function getAddress(): ?string
    {
        return $this->safeString($this->dataGet('transaction.receiver'));
    }

    /**
     * Внешний ID выплаты у провайдера.
     * Для Exnode это tracker_id.
     */
    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('transaction.tracker_id'))
            ?? $this->safeString($this->queryGet('tracker_id'));
    }

    /**
     * Сумма выплаты.
     */
    public function getAmount(): ?string
    {
        return $this->safeDecimal($this->dataGet('transaction.amount'));
    }

    /**
     * Валюта выплаты (token).
     */
    public function getCurrencyWithNetwork(): ?string
    {
        return $this->safeString($this->dataGet('transaction.token'));
    }


    public function getOrderId()
    {
        return $this->safeString($this->dataGet('transaction.client_transaction_id'));
    }


    public function getErrorMessage(): ?string
    {
        if (!$this->exists()) {
            return 'Транзакция не найдена';
        }

        if ($this->isSuccessful() || $this->isPending()) {
            return null;
        }

        $msg = $this->safeString($this->dataGet('message'))
            ?? $this->safeString($this->dataGet('error'))
            ?? $this->safeString($this->dataGet('error_message'));

        if ($msg !== null && $msg !== '') {
            return $msg;
        }

        $status = $this->getStatus();
        return $status ? ('Статус: ' . $status) : 'Не удалось определить статус выплаты';
    }
}
