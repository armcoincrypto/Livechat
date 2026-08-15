<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Goldex\Messages;

use iEXPackages\Payments\Core\Contracts\FiatPayoutResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;
use iEXPackages\Payments\Core\Traits\FiatPayoutTrackingTrait;

final class PayoutResponse extends AbstractResponse implements FiatPayoutResponseInterface
{
    use FiatPayoutTrackingTrait;

    /**
     * Выплата считается успешно СОЗДАННОЙ (не завершённой),
     * если API вернул data.requestId.
     *
     * Старое поведение:
     * - status = 0
     * - withdrawal_id = response['data']['requestId']
     * - defer_success = true
     */
    public function isSuccessful(): bool
    {
        return $this->getWithdrawalId() !== null;
    }

    /**
     * Уникальный ID выплаты в системе провайдера (requestId).
     */
    public function getWithdrawalId(): ?string
    {
        $id = $this->safeString($this->dataGet('data.requestId'));

        return ($id !== null && $id !== '') ? $id : null;
    }

    /**
     * payout принят → дальше ждём финальный статус позже (cron).
     */
    public function isFiatPayoutTrackingRequired(): bool
    {
        return $this->isSuccessful();
    }

    /**
     * Отложенный успех: payout создан, но финал будет позже.
     */
    public function isDeferredSuccess(): bool
    {
        return $this->isSuccessful();
    }

    /**
     * Как отслеживаем выплату.
     */
    public function getTrackingMode(): string
    {
        return 'cron';
    }

    /**
     * Статус (если API отдаёт), иначе fallback.
     */
    public function getStatus(): ?string
    {
        $status = $this->safeString($this->dataGet('status'));
        $status = $status !== null ? strtolower($status) : null;

        return ($status !== null && $status !== '') ? $status : ($this->isSuccessful() ? 'success' : null);
    }

    /**
     * Текст ошибки, если payout не создан.
     */
    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful()) {
            return null;
        }

        return
            $this->safeString($this->dataGet('error'))
            ?? $this->safeString($this->dataGet('message'))
            ?? $this->safeString($this->dataGet('data.error'))
            ?? $this->safeString($this->dataGet('data.message'))
            ?? 'Не удалось создать выплату';
    }
}
