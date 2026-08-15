<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Heleket\Messages;

use iEXPackages\Payments\Core\Contracts\BlockchainPaymentResponseInterface;
use iEXPackages\Payments\Core\Contracts\FetchPaymentResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class FetchPaymentResponse extends AbstractResponse implements FetchPaymentResponseInterface, BlockchainPaymentResponseInterface
{
// Incoming (payment)
    public const STATUS_CHECK                = 'check';
    public const STATUS_CONFIRM_CHECK        = 'confirm_check';
    public const STATUS_PROCESS              = 'process';
    public const STATUS_LOCKED               = 'locked';

    public const STATUS_PAID                 = 'paid';
    public const STATUS_PAID_OVER            = 'paid_over';

    public const STATUS_WRONG_AMOUNT         = 'wrong_amount';
    public const STATUS_WRONG_AMOUNT_WAITING = 'wrong_amount_waiting';

    public const STATUS_FAIL                 = 'fail';
    public const STATUS_CANCEL               = 'cancel';
    public const STATUS_SYSTEM_FAIL           = 'system_fail';


    private const array STATUSES_SUCCESS = [
        self::STATUS_PAID,
        self::STATUS_PAID_OVER,
    ];

    private const array STATUSES_PENDING = [
        self::STATUS_CHECK,
        self::STATUS_LOCKED,
        self::STATUS_CONFIRM_CHECK,
        self::STATUS_PROCESS,
    ];

    private const array STATUSES_CANCELLED = [
        self::STATUS_FAIL,
        self::STATUS_CANCEL,
        self::STATUS_SYSTEM_FAIL,
        self::STATUS_WRONG_AMOUNT,
        self::STATUS_WRONG_AMOUNT_WAITING
    ];

    private const array STATUS_DESCRIPTIONS = [
        self::STATUS_CHECK =>
            'Ожидание появления транзакции в блокчейне',

        self::STATUS_CONFIRM_CHECK =>
            'Транзакция найдена, ожидается подтверждение сети',

        self::STATUS_PROCESS =>
            'Платёж в процессе обработки',

        self::STATUS_LOCKED =>
            'Средства заблокированы из-за AML',

        self::STATUS_PAID =>
            'Платёж успешно выполнен',

        self::STATUS_PAID_OVER =>
            'Платёж выполнен с переплатой',

        self::STATUS_WRONG_AMOUNT =>
            'Клиент оплатил меньшую сумму, чем требовалось',

        self::STATUS_WRONG_AMOUNT_WAITING =>
            'Оплачена недостаточная сумма, ожидается доплата',

        self::STATUS_FAIL =>
            'Ошибка при оплате',

        self::STATUS_CANCEL =>
            'Платёж отменён клиентом',

        self::STATUS_SYSTEM_FAIL =>
            'Произошла системная ошибка',
    ];

    public function canRegisterTransaction(): bool
    {
        return true;
    }

    /**
     * Платёж найден у провайдера (запись существует).
     * Не равно "успешно оплачен".
     */
    public function exists(): bool
    {
        if ((int) ($this->dataGet('state') ?? 1) !== 0) {
            return false;
        }

        $uuid = $this->safeString($this->dataGet('result.uuid'));

        return $uuid !== null && $uuid !== '';
    }

    /**
     * Статус Heleket: обычно result.payment_status или result.status.
     */
    public function getStatus(): ?string
    {
        $status = $this->safeString($this->dataGet('result.payment_status'))
            ?? $this->safeString($this->dataGet('result.status'));

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

    /**
     * Сумма, которую отправил плательщик.
     * В ответе Heleket это result.payer_amount (приоритет),
     * fallback: payment_amount, amount.
     */
    public function getAmount(): ?string
    {
        return $this->safeDecimal(
            $this->dataGet('result.payment_amount')
        );
    }

    /**
     * Валюта, которой платит клиент (payer_currency) либо currency.
     */
    public function getCurrency(): ?string
    {
        return $this->safeString(
            $this->dataGet('result.currency')
        );
    }


    /**
     * Внешний ID у провайдера (uuid).
     */
    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('result.uuid'));
    }

    /**
     * Внутренний orderId:
     * - query.transactionId / query.order_id
     * - fallback: result.order_id
     */
    public function getOrderId(): ?string
    {
        return $this->safeString($this->dataGet('result.order_id'));
    }

    /**
     * txid из Heleket.
     */
    public function getTransactionHash(): ?string
    {
        return $this->safeString($this->dataGet('result.txid'));
    }

    /**
     * Сообщение об ошибке (если не success/pending).
     */
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
        return $status ? ('Статус: ' . $status) : 'Не удалось определить статус платежа';
    }

    public function getStatusDescription(): string
    {
        if (!$this->exists()) {
            return 'Транзакция не найдена';
        }

        $status = $this->getStatus();

        if ($status === null) {
            return 'Статус не определён';
        }

        return self::STATUS_DESCRIPTIONS[$status] ?? 'Статус не определён';
    }
}
