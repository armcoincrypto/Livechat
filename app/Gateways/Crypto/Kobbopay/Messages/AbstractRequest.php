<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Kobbopay\Messages;

use App\Gateways\Crypto\Kobbopay\KobbopayMerchantApiPath;
use App\Gateways\Crypto\Kobbopay\Messages\Traits\GeneratedMerchantInputs;
use App\Gateways\Crypto\Kobbopay\Services\SecureSignatureService;
use iEXPackages\Payments\Core\Engine\AbstractRequest as BaseAbstractRequest;

abstract class AbstractRequest extends BaseAbstractRequest
{
    use GeneratedMerchantInputs;

    protected function endpointMode(): string
    {
        return 'CUSTOM';
    }

    protected function endpointCustomBaseUrl(): string
    {
        $fromVault = trim($this->configString('api_base_url', ''));
        if ($fromVault !== '') {
            return rtrim($fromVault, '/');
        }

        $fromEnv = trim((string) config('kobbopay.api_base', ''));
        if ($fromEnv !== '') {
            return rtrim($fromEnv, '/');
        }

        return 'https://merchant.kobbex.com';
    }

    protected function tokenCatalogPath(): string
    {
        return KobbopayMerchantApiPath::tokenFetch($this->endpointCustomBaseUrl());
    }

    protected function httpConfig(): array
    {
        return [
            'baseUrl' => $this->endpointUrl('/'),

            'timeout'      => 15,
            'retries'      => 3,
            'retryDelayMs' => 1000,

            'beforeSend' => function (string $method, string $path, array $data, array $headers) {
                $publicKey  = $this->getConnectionValue('getPublicKey');
                $privateKey = $this->getConnectionValue('getPrivateKey');

                $signatureService = new SecureSignatureService($privateKey);

                // Exnode-compatible auth: GET requests sign an empty body even when
                // query params are sent separately via Laravel Http.
                $signatureBody = strtolower($method) === 'get' ? null : $data;

                $headers['Accept']    = 'application/json';
                $headers['ApiPublic'] = $publicKey;
                $headers['Signature'] = $signatureService->generateSignature($signatureBody);
                $headers['Timestamp'] = $signatureService->getTimestamp();

                return [$path, $data, $headers];
            },

            'afterResponse' => function (array $json, int $httpStatus) {
                return $json;
            },
        ];
    }
}
