<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Heleket\Messages;

use iEXPackages\Payments\Core\Contracts\RedirectResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;
use iEXPackages\Payments\Core\Traits\RedirectResponseTrait;

/**
 * Ответ на создание платежа (incoming).
 */
final class PurchaseRedirectResponse extends AbstractResponse implements RedirectResponseInterface
{
    use RedirectResponseTrait;

    public function isSuccessful(): bool
    {
        return (int)($this->dataGet('state') ?? 1) === 0
            && $this->safeString($this->dataGet('result.uuid')) !== null;
    }

    public function isRedirect(): bool
    {
        return true;
    }

    public function getRedirectMethod(): string
    {
        return 'GET';
    }

    public function getRedirectUrl(): ?string
    {
        return $this->safeString($this->dataGet('result.url'));
    }

    public function getRedirectData(): array
    {
        return [];
    }

    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('result.uuid'));
    }

    public function getErrorMessage(): ?string
    {
        return $this->getRedirectUrl() ? null : 'Heleket не вернул URL для редиректа.';
    }
}
