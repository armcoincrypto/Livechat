<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\NicePay\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;
use iEXPackages\Payments\Core\Contracts\RedirectResponseInterface;
use iEXPackages\Payments\Core\Traits\RedirectResponseTrait;

final class PurchaseResponse extends AbstractResponse implements RedirectResponseInterface
{
    use RedirectResponseTrait;

    /**
     * Для создания платежа
     * НЕ означает успех — это редирект.
     */
    public function isSuccessful(): bool
    {
        return false;
    }

    /**
     * Ответ требует редиректа пользователя.
     */
    public function isRedirect(): bool
    {
        return true;
    }

    /**
     * Метод редиректа.
     */
    public function getRedirectMethod(): string
    {
        return 'GET';
    }

    /**
     * URL редиректа.
     *
     * В старой версии лежал в поле `message`.
     */
    public function getRedirectUrl(): ?string
    {
        return $this->safeString($this->dataGet('url'));
    }

    /**
     * Данные для POST-редиректа (не используются).
     */
    public function getRedirectData(): array
    {
        return [];
    }

    /**
     * Ошибка, если URL отсутствует.
     */
    public function getErrorMessage(): ?string
    {
        return $this->safeString($this->dataGet('data.message'))
            ?? 'NicePay: ошибка создания платежа';
    }

    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('payment_id'));
    }
}
