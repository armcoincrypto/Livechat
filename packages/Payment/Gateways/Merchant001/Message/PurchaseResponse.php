<?php

namespace iEXPackages\Payment\Gateways\Merchant001\Message;

use iEXPackages\Payment\Engines\Message\AbstractResponse;
use iEXPackages\Payment\Engines\Message\RedirectResponseInterface;
use iEXPackages\Payment\Engines\Message\RequestInterface;

class PurchaseResponse extends AbstractResponse implements RedirectResponseInterface
{
    public function __construct(RequestInterface $request, $data)
    {
        parent::__construct($request, $data);
    }

    public function isSuccessful(): bool
    {
        return false;
    }

    public function isRedirect(): bool
    {
        return true;
    }

    public function getRedirectUrl(): ?string
    {
        if (isset($this->data['transaction']['paymentUrl'])) {
            return $this->data['transaction']['paymentUrl'];
        }

        return '';
    }

    public function getRedirectMethod(): string
    {
        return 'GET';
    }

    public function getRedirectData(): array
    {
        return [];
    }

    public function getLabel()
    {
        return $this->data['transaction']['invoiceId'];
    }

    public function getIdFromMerchant(): string
    {
        return $this->data['transaction']['id'];
    }
}
