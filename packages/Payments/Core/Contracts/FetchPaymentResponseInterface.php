<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Contracts;

/**
 * Контракт ответа на проверку входящего платежа (polling / fetchPayment).
 *
 * Интерфейс отделяет:
 * - факт существования платежа
 * - от его бизнес-статуса (pending / success / cancelled).
 */
interface FetchPaymentResponseInterface
{
    /**
     * Существует ли платёж в системе провайдера.
     *
     * См. подробное описание в реализации.
     */
    public function exists(): bool;

    /**
     * Находится ли платёж в ожидании обработки.
     */
    public function isPending(): bool;

    /**
     * Завершён ли платёж успешно.
     */
    public function isSuccessful(): bool;

    /**
     * Был ли платёж отменён / отклонён.
     */
    public function isCancelled(): bool;
}
