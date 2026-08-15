<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Volet\Messages;

use iEXPackages\Payments\Core\Contracts\RedirectResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;
use iEXPackages\Payments\Core\Traits\RedirectResponseTrait;

final class PurchaseResponse extends AbstractResponse implements RedirectResponseInterface
{
    use RedirectResponseTrait;

    public function isSuccessful(): bool
    {
        // Для твоей системы лучше считать “успешно”, если мы смогли подготовить форму.
        // Иначе общий код может подумать, что это ошибка.
        return $this->getRedirectUrl() !== null && $this->getRedirectUrl() !== '';
    }

    public function isRedirect(): bool
    {
        return true;
    }

    public function getRedirectMethod(): string
    {
        return 'POST';
    }

    public function getRedirectUrl(): ?string
    {
        return 'https://account.volet.com/sci/';
    }

    public function getRedirectData(): array
    {
        // Всё, что надо отправить в форму
        return $this->getData();
    }

    public function getExternalId(): ?string
    {
        return null;
    }

    public function getErrorMessage(): ?string
    {
        return $this->isSuccessful() ? null : 'Volet: не удалось сформировать redirect-форму.';
    }
}
