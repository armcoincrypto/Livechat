<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Rapira\Messages;

use App\Gateways\Crypto\Rapira\Messages\Traits\GeneratedMerchantInputs;
use App\Gateways\Crypto\Rapira\Messages\Traits\GeneratedPayInputs;
use App\Gateways\Crypto\Rapira\Services\JwtTokenService;
use iEXPackages\Payments\Core\Engine\AbstractRequest as BaseAbstractRequest;
use iEXPackages\Payments\Core\Support\UrlHelper;

/**
 * Базовый Request-класс только для шлюза Rapira.
 *
 * Все Rapira-запросы (Purchase, CompletePurchase, Payout)
 * будут наследовать этот класс.
 *
 * Здесь мы:
 *   - подключаем автоматически сгенерированный trait GeneratedMerchantInputs
 *   - можем добавить Rapira-специфичные методы (подпись, нормализация данных, и т.п)
 */
abstract class AbstractRequest extends BaseAbstractRequest
{
    use GeneratedMerchantInputs,
        GeneratedPayInputs;


    protected function endpointStaticBaseUrl(): string
    {
        if ($this->isMerchantContext()) {
            return UrlHelper::toHttpsBaseUrl($this->getApiHost());
        }

        return UrlHelper::toHttpsBaseUrl($this->getPayApiHost());
    }


    /**
     * HTTP конфиг для Rapira.
     *
     * Важно:
     * - токен JWT генерируем на каждый запрос
     * - в headers добавляем Authorization: Bearer <token>
     * - baseUrl строим как https://{host}
     */
    protected function httpConfig(): array
    {
        return [
            'baseUrl' => $this->endpointUrl('/'),

            'timeout' => 10,
            'retries' => 3,
            'retryDelayMs' => 1000,

            'beforeSend' => function (string $method, string $path, array $data, array $headers) {

                $host = UrlHelper::toBaseUrl((string) $this->endpointStaticBaseUrl());

                $jwtService = $this->isMerchantContext()
                    ? new JwtTokenService(
                        privateKey: $this->getPrivateKey(),
                        uuid:       $this->getUuid(),
                        apiHost:    $host,
                    )
                    : new JwtTokenService(
                        privateKey: $this->getPayPrivateKey(),
                        uuid:       $this->getPayUuid(),
                        apiHost:    $host,
                    );

                $headers['Authorization'] = 'Bearer ' . $jwtService->generate();

                return [$path, $data, $headers];
            },
        ];
    }

    public function getRapiraNonce(): string
    {
        $response = $this->sendRequest('get', '/open/system/time');

        // Rapira возвращает JSON вида: {"serverTime": 1714526426667}
        // Но на некоторых клиентах/реализациях может вернуться и просто число.
        if (is_array($response)) {
            $serverTime = $response['serverTime'] ?? null;

            if (!is_numeric($serverTime)) {
                throw new \RuntimeException('Rapira time API returned invalid serverTime');
            }

            return (string) $serverTime;
        }

        if (is_object($response)) {
            $serverTime = $response->serverTime ?? null;

            if (!is_numeric($serverTime)) {
                throw new \RuntimeException('Rapira time API returned invalid serverTime');
            }

            return (string) $serverTime;
        }

        if (!is_numeric($response)) {
            throw new \RuntimeException('Rapira time API returned invalid nonce');
        }

        return (string) $response;
    }
}
