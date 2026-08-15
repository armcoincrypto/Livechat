<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Exnode\Messages;

use App\Gateways\Crypto\Exnode\Services\SecureSignatureService;
use iEXPackages\Payments\Core\Engine\AbstractRequest as BaseAbstractRequest;
use App\Gateways\Crypto\Exnode\Services\HttpClient;

/**
 * Базовый Request для шлюза Exnode.
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
        return 'https://my.exnode.io';
    }

    /**
     * Пример httpConfig() для шлюза, где нужна подпись:
     *  - ApiPublic (public_key)
     *  - Signature (HMAC/сервис подписи)
     *  - Timestamp
     * + проверка ответа: если есть numeric status -> ошибка
     */
    protected function httpConfig(): array
    {
        return [
            // baseUrl должен быть абсолютным URL
            'baseUrl' => $this->endpointUrl('/'),

            'timeout'      => 15,
            'retries'      => 3,
            'retryDelayMs' => 1000,

            /**
             * beforeSend:
             *  - формируем подпись
             *  - добавляем заголовки
             * return [$path, $data, $headers]
             */
            'beforeSend' => function (string $method, string $path, array $data, array $headers) {

                // ✅ ключи берём через контекст (merchant/pay)
                // если у тебя генератор сделал getPayPublicKey/getPayPrivateKey, то:
                // getConnectionString('public_key') автоматом вызовет getPayPublicKey() в pay-контексте
                $publicKey  = $this->getConnectionValue('getPublicKey');
                $privateKey = $this->getConnectionValue('getPrivateKey');

                $signatureService = new SecureSignatureService($privateKey);

                $headers['Accept']    = 'application/json';
                $headers['ApiPublic'] = $publicKey;
                $headers['Signature'] = $signatureService->generateSignature($data);
                $headers['Timestamp'] = $signatureService->getTimestamp();

                return [$path, $data, $headers];
            },

            /**
             * afterResponse:
             *  - старая логика: если status есть и numeric -> это ошибка
             */
            'afterResponse' => function (array $json, int $httpStatus) {
                // Раньше при numeric status бросали Exception с полным JSON — ломало UX и рисковало логами.
                // Логические ответы API отдаём в Response (isSuccessful()/getErrorMessage()).
                return $json;
            },
        ];
    }
}
