<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\IvanPay\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class FetchPayoutResponse extends AbstractResponse
{
    private const STATUS_CREATED     = 'created';
    private const STATUS_IN_PROGRESS = 'in_progress';
    private const STATUS_COMPLETED   = 'completed';
    private const STATUS_FAILED      = 'failed';

    /**
     * Статус выплаты (строковый, lower-case).
     */
    public function getStatus(): ?string
    {
        $status = $this->safeString($this->dataGet('payment.status'));
        $status = $status !== null ? strtolower(trim($status)) : null;

        return $status !== '' ? $status : null;
    }


    /**
     * Выплата успешна, если payment.status == completed.
     */
    public function isSuccessful(): bool
    {
        return $this->isResponseOk() && $this->getStatus() === self::STATUS_COMPLETED;
    }

    /**
     * Pending, если created или in_progress.
     */
    public function isPending(): bool
    {
        return $this->isResponseOk() && in_array($this->getStatus(), [
                self::STATUS_CREATED,
                self::STATUS_IN_PROGRESS,
            ], true);
    }

    /**
     * Cancelled/Failed, если failed.
     */
    public function isCancelled(): bool
    {
        return $this->isResponseOk() && $this->getStatus() === self::STATUS_FAILED;
    }

    /**
     * Внешний ID выплаты в провайдере (payment.id).
     * Это то, что ты хранишь как id_from_pay / withdrawal_id.
     */
    public function getWithdrawalId(): ?string
    {
        $id = $this->dataGet('payment.id');

        return is_numeric($id) ? (string) $id : $this->safeString($id);
    }

    public function getExternalId(): ?string
    {
        return $this->getWithdrawalId();
    }

    /**
     * Описание статуса для UI.
     */
    public function getStatusDescription(): string
    {
        return match ($this->getStatus()) {
            self::STATUS_CREATED     => 'Выплата создана и ожидает обработки',
            self::STATUS_IN_PROGRESS => 'Выплата в процессе',
            self::STATUS_COMPLETED   => 'Выплата успешно выполнена',
            self::STATUS_FAILED      => 'Выплата завершилась ошибкой',
            default                  => $this->isResponseOk() ? 'Статус не определён' : 'Не удалось получить данные выплаты',
        };
    }

    /**
     * Текст ошибки.
     */
    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful() || $this->isPending()) {
            return null;
        }

        // IvanPay может отдавать error/message наверху
        $msg = $this->safeString($this->dataGet('error'))
            ?? $this->safeString($this->dataGet('message'))
            ?? $this->safeString($this->dataGet('error_message'));

        if ($msg !== null && $msg !== '') {
            return $msg;
        }

        $s = $this->getStatus();
        return $s ? ('Статус выплаты: ' . $s) : 'Не удалось определить статус выплаты';
    }


    /**
     * Проверяем, что ответ валиден (верхний status == 200 и payment.id есть).
     */
    private function isResponseOk(): bool
    {
        $status = $this->dataGet('status');
        if (!is_numeric($status) || (int) $status !== 200) {
            return false;
        }

        $id = $this->dataGet('payment.id');

        return is_numeric($id) && (int) $id > 0;
    }
}
