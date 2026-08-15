<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Kobbopay\Messages\Traits;

/**
 * @mixin \iEXPackages\Payments\Core\Engine\AbstractRequest
 */
trait GeneratedMerchantInputs
{
    public function getPrivateKey(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('merchant')->get('private_key');

        return $value === null ? null : (string) $value;
    }

    public function getPublicKey(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('merchant')->get('public_key');

        return $value === null ? null : (string) $value;
    }

    public function getApiBaseUrl(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('merchant')->get('api_base_url');

        return $value === null ? null : (string) $value;
    }

    public function getWebhookSecret(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('merchant')->get('webhook_secret');

        return $value === null ? null : (string) $value;
    }
}
