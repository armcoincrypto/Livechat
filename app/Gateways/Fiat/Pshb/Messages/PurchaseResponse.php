<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Pshb\Messages;

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
     * В новой версии лежит в поле `redirect_url`.
     */
    public function getRedirectUrl(): ?string
    {
        return $this->safeString($this->dataGet('redirect_url'));
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
            ?? 'PSHB: ошибка формирования ссылки оплаты';
    }

    public function getExternalId(): ?string
    {
        // В redirect-based реализации payment_id не возвращается
        return null;
    }
}
