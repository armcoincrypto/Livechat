<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\IvanPay\Messages;

use iEXPackages\Payments\Core\Contracts\FiatPayoutResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;
use iEXPackages\Payments\Core\Traits\FiatPayoutTrackingTrait;

final class PayoutResponse extends AbstractResponse implements FiatPayoutResponseInterface
{
    use FiatPayoutTrackingTrait;

    /**
     * Выплата успешно СОЗДАНА (не завершена),
     * если у провайдера есть payment.id и status == 200.
     *
     * Пример успеха:
     * [
     *   'status' => 200,
     *   'payment' => ['id' => 648145, 'status' => 'in_progress', ...]
     * ]
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
     * Нужно ли ставить выплату на дальнейший трекинг (cron).
     * Для IvanPay: да, если payout создан.
     */
    public function isFiatPayoutTrackingRequired(): bool
    {
        return $this->isSuccessful();
    }

    public function getStatus(): ?string
    {
        return $this->isSuccessful() ? 'success' : null;
    }

    /**
     * Уникальный ID выплаты в системе провайдера.
     * В IvanPay это payment.id.
     */
    public function getWithdrawalId(): ?string
    {
        $id = $this->dataGet('payment.id');

        if (is_numeric($id)) {
            return (string) $id;
        }

        return $this->safeString($id);
    }

    /**
     * Как отслеживаем выплату.
     * Для IvanPay: cron polling.
     */
    public function getTrackingMode(): string
    {
        return 'cron';
    }

    /**
     * Успех отложенный.
     * Старый флаг: defer_success => true
     */
    public function isDeferredSuccess(): bool
    {
        return $this->isSuccessful();
    }

    /**
     * Текст ошибки, если payout не создан.
     *
     * Пример ошибки:
     * [
     *   'status' => 500,
     *   'error' => '...',
     *   'error_code' => 5022
     * ]
     */
    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful()) {
            return null;
        }

        $msg = $this->safeString($this->dataGet('error'))
            ?? $this->safeString($this->dataGet('message'))
            ?? $this->safeString($this->dataGet('error_message'));

        if ($msg !== null && $msg !== '') {
            return $msg;
        }

        $code = $this->dataGet('error_code');
        if (is_numeric($code)) {
            return 'Ошибка выплаты. Код: ' . (string) $code;
        }

        $status = $this->dataGet('status');
        if (is_numeric($status)) {
            return 'Ошибка выплаты. HTTP status: ' . (string) $status;
        }

        return 'Ошибка выплаты';
    }
}
