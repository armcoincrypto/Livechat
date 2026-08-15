<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Firekassa\Messages;

use iEXPackages\Payments\Core\Contracts\FiatPayoutResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;
use iEXPackages\Payments\Core\Traits\FiatPayoutTrackingTrait;

final class PayoutResponse extends AbstractResponse implements FiatPayoutResponseInterface
{
    use FiatPayoutTrackingTrait;

    /**
     * Выплата считается успешно СОЗДАННОЙ (не завершённой),
     * если Firekassa вернул ID выплаты.
     *
     * Старое поведение: status=0 + withdrawal_id=response['id']
     */
    public function isSuccessful(): bool
    {
        $id = $this->dataGet('id');

        return is_numeric($id) && (int) $id > 0;
    }

    /**
     * Уникальный ID выплаты в системе провайдера.
     *
     * Старое поведение: withdrawal_id = response['id']
     */
    public function getWithdrawalId(): ?string
    {
        $id = $this->dataGet('id');

        if (!is_numeric($id) || (int) $id <= 0) {
            return null;
        }

        return (string) (int) $id;
    }

    /**
     * Нужно ли дальше отслеживать выплату.
     * payout принят -> ждём финальный статус позже (cron).
     */
    public function isFiatPayoutTrackingRequired(): bool
    {
        return $this->isSuccessful() && $this->isDeferredSuccess();
    }

    /**
     * Отложенный успех:
     * payout принят, но финальный статус будет позже (cron).
     *
     * Если позже Firekassa начнёт отдавать финальный статус в этом же ответе —
     * сможешь поменять логику: return !$this->isFinal();
     */
    public function isDeferredSuccess(): bool
    {
        return true;
    }

    public function getTrackingMode(): string
    {
        return 'cron';
    }

    /**
     * Статус в терминах шлюза (если есть), иначе fallback.
     */
    public function getStatus(): ?string
    {
        $status = $this->safeString($this->dataGet('status'));
        $status = $status !== null ? strtolower($status) : null;

        if ($status !== null && $status !== '') {
            return $status;
        }

        return $this->isSuccessful() ? 'success' : null;
    }

    /**
     * Текст ошибки, если payout не создан.
     */
    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful()) {
            return null;
        }

        return $this->safeString($this->dataGet('payment_error'))
            ?? $this->safeString($this->dataGet('message'))
            ?? $this->safeString($this->dataGet('error'))
            ?? 'Ошибка выплаты';
    }
}
