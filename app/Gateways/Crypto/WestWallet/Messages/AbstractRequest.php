<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\WestWallet\Messages;

use iEXPackages\Payments\Core\Engine\AbstractRequest as BaseAbstractRequest;

abstract class AbstractRequest extends BaseAbstractRequest
{
    use Traits\GeneratedMerchantInputs,
        Traits\GeneratedPayInputs;

    /**
     * Базовый endpoint шлюза.
     */
    protected function endpointStaticBaseUrl(): string
    {
        // может быть переопределён api_host в конфиге
        return 'https://api.westwallet.io';
    }

    /**
     * HTTP-конфигурация для WestWallet.
     *
     * Особенности:
     * - X-API-KEY (public key)
     * - X-ACCESS-SIGN + X-ACCESS-TIMESTAMP
     * - подпись = HMAC_SHA256(timestamp + body, private_key)
     */
    protected function httpConfig(): array
    {
        return [
            'baseUrl' => $this->endpointUrl('/'),

            'timeout' => 10,
            'retries' => 3,
            'retryDelayMs' => 1000,

            'headers' => [
                'X-API-KEY'    => $this->getConnectionValue('getPublicKey'),
                'Content-Type'=> 'application/json',
            ],

            /**
             * beforeSend вызывается ПЕРЕД отправкой запроса
             * и может менять path, data, headers
             */
            'beforeSend' => function (
                string $method,
                string $path,
                array $data,
                array $headers
            ) {
                $timestamp = time();

                $body = !empty($data)
                    ? json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : '';

                $signature = hash_hmac(
                    'sha256',
                    (string) $timestamp . $body,
                    $this->getConnectionValue('getPrivateKey'),
                );

                $headers['X-ACCESS-SIGN']      = $signature;
                $headers['X-ACCESS-TIMESTAMP'] = (string) $timestamp;

                return [$path, $data, $headers];
            },
        ];
    }
}
