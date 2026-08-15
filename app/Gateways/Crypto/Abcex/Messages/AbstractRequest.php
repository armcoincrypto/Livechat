<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Abcex\Messages;

use iEXPackages\Payments\Core\Engine\AbstractRequest as BaseAbstractRequest;
use App\Gateways\Crypto\Abcex\Services\HttpClient;

/**
 * Базовый Request для шлюза Abcex.
 *
 * Здесь:
 * - подключаются Generated*Inputs (позже генератором)
 * - задаётся базовый endpoint (можно статически или из конфига)
 * - создаётся HttpClient и единый callApi() для всех операций
 */
abstract class AbstractRequest extends BaseAbstractRequest
{
    // Пример подключения (когда сгенерируешь):
    use Traits\GeneratedMerchantInputs,
        Traits\GeneratedPayInputs;

    /**
     * Базовый URL (по умолчанию шаблонный).
     *
     * В каждом шлюзе ты сам решаешь:
     * - статический хост: вернуть 'https://example.com'
     * - из конфига: return 'https://' . $this->configString('api_host')
     * - кастомно: переопределить как нужно
     */
    protected function endpointStaticBaseUrl(): string
    {
        return 'https://gateway.abcex.io';
    }

    /**
     * Конфигурация HTTP транспорта для Abcex.
     */
    protected function httpConfig(): array
    {
        return [
            'baseUrl' => $this->endpointUrl('/'),
            'token' => $this->isMerchantContext()
                ? $this->getApiKey()
                : $this->getPayApiKey(),

            'timeout' => 10,
            'retries' => 3,
            'retryDelayMs' => 1000,

            /**
             * Хук до отправки (редко нужен для Abcex)
             * return [$path, $data, $headers]
             */
            'beforeSend' => function (string $method, string $path, array $data, array $headers) {
                return [$path, $data, $headers];
            },

            /**
             * Хук после ответа: здесь реализуем старую логику
             * "если statusCode есть — это ошибка"
             */
            'afterResponse' => function (array $json, int $httpStatus) {
                if (isset($json['statusCode'])) {
                    $message = (string)($json['message'] ?? 'Ошибка Abcex');
                    throw new \Exception($message);
                }

                return $json;
            },
        ];
    }
}
