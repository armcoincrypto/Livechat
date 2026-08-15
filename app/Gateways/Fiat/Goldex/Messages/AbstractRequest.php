<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Goldex\Messages;

use iEXPackages\Payments\Core\Engine\AbstractRequest as BaseAbstractRequest;

/**
 * Базовый Request для шлюза Obmenkaclub.
 *
 * Здесь:
 * - подключаются Generated*Inputs (позже генератором)
 * - задаётся базовый endpoint (можно статически или из конфига)
 * - создаётся HttpClient и единый callApi() для всех операций
 */
abstract class AbstractRequest extends BaseAbstractRequest
{
    use Traits\GeneratedPayInputs;

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
        return 'https://stage.goldex.space';
    }

    /**
     * HTTP-конфигурация ObmenkaClub.
     *
     * Особенности:
     * - авторизация через заголовок auth-token
     * - ответы API содержат status/data
     */
    protected function httpConfig(): array
    {
        return [
            'baseUrl' => $this->endpointUrl('/'),

            'headers' => [
                'Accept'     => 'application/json',
                'auth-token' => $this->getPayApiKey()
            ],

            'timeout'      => 10,
            'retries'      => 3,
            'retryDelayMs' => 1000,

            // Нормализация ошибок API в единый формат (и чтобы лог было понятнее читать)
            'afterResponse' => function (array $json, int $httpStatus): array {
                // если API вернул ошибку в своем формате — не ломаем структуру, просто вернем как есть
                return $json;
            },
        ];
    }
}
