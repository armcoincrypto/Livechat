<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\AlfabitPay\Messages;

use iEXPackages\Payments\Core\Contracts\FetchPaymentResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class FetchPaymentResponse extends AbstractResponse  implements FetchPaymentResponseInterface
{
    protected const STATUS_SUCCESS = 'success';

    protected const STATUSES_PENDING = [
        'created',
        'invoiceWaitCreate',
        'invoiceWaitRequisites',
        'invoiceWaitPay',
        'invoiceWaitCheck',
        'inProgress',
        'transferWaitDone',
        'transferWaitCheck',
        'waitNextHop',
    ];

    protected const STATUSES_CANCELLED = [
        'failed',
        'invoiceNotCreated',
        'invoiceNotPayed',
        'invoiceCheckBlocked',
        'exchangeBlocked',
    ];

    public function isRegisterPayment(): bool
    {
        return true;
    }

    public function exists(): bool
    {
        $data = $this->dataGet('data');
        return is_array($data) && $data !== [];
    }

    /**
     * Найден ли объект платежа в системе (существует ли запись).
     */
    public function isFindPayment(): bool
    {
        $data = $this->dataGet('data');
        return is_array($data) && $data !== [];
    }

    /**
     * Статус транзакции в терминах AlfabitPay.
     */
    public function getStatus(): ?string
    {
        return $this->safeString($this->dataGet('data.status'));
    }

    public function isSuccessful(): bool
    {
        return $this->getStatus() === self::STATUS_SUCCESS;
    }

    public function isPending(): bool
    {
        return in_array($this->getStatus(), self::STATUSES_PENDING, true);
    }

    public function isCancelled(): bool
    {
        return in_array($this->getStatus(), self::STATUSES_CANCELLED, true);
    }

    /**
     * Сумма платежа (фактическая).
     */
    public function getAmount(): ?string
    {
        return $this->safeDecimal($this->dataGet('data.amountInFact'));
    }

    /**
     * Валюта платежа.
     */
    public function getCurrency(): ?string
    {
        return $this->safeString($this->dataGet('data.currencyInCode'));
    }

    /**
     * Внешний ID платежа в системе шлюза (UID).
     *
     * В invoice-API AlfabitPay это обычно data.uid.
     */
    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('data.uid'))
            ?? $this->safeString($this->queryGet('externalId'));
    }

    /**
     * Текст ошибки.
     */
    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful() || $this->isPending()) {
            return null;
        }

        // иногда ошибка может быть внутри data.message или на верхнем уровне
        $msg = $this->safeString($this->dataGet('data.message'))
            ?? $this->safeString($this->dataGet('message'))
            ?? $this->safeString($this->dataGet('error'))
            ?? $this->safeString($this->dataGet('error_message'));

        if ($msg !== null && $msg !== '') {
            return $msg;
        }

        $status = $this->getStatus();
        return $status ? ('Статус: ' . $status) : 'Не удалось определить статус платежа';
    }

    /**
     * Хэш транзакции (если отдаёт).
     */
    public function getTransactionHash(): ?string
    {
        return $this->safeString($this->dataGet('data.txId'));
    }

    /**
     * Описание статуса для UI.
     */
    public function getStatusDescription(): string
    {
        return match ($this->getStatus()) {
            self::STATUS_SUCCESS => 'Транзакция успешно выполнена',

            'created'              => 'Заявка создана, ожидаем формирования инвойса',
            'invoiceWaitCreate'    => 'Инвойс создаётся, ожидайте',
            'invoiceWaitRequisites'=> 'Ожидаем реквизиты для оплаты',
            'invoiceWaitPay'       => 'Ожидаем оплату',
            'invoiceWaitCheck'     => 'Проверка поступления средств',
            'inProgress'           => 'Операция в процессе выполнения',
            'transferWaitDone'     => 'Ожидаем завершения перевода',
            'transferWaitCheck'    => 'Проверяем перевод',
            'waitNextHop'          => 'Ожидаем следующий этап обработки',

            'failed'               => 'Транзакция завершилась с ошибкой',
            'invoiceNotCreated'    => 'Инвойс не создан',
            'invoiceNotPayed'      => 'Инвойс не оплачен',
            'invoiceCheckBlocked'  => 'Проверка инвойса заблокирована',
            'exchangeBlocked'      => 'Операция заблокирована',

            default => 'Статус не определен',
        };
    }
}
