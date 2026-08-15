<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\CryptoCash\Messages;

use iEXPackages\Payments\Core\Engine\AbstractRequest as BaseAbstractRequest;

/**
 * Базовый Request для шлюза CryptoCash.
 *
 * Здесь:
 * - подключаются Generated*Inputs (позже генератором)
 * - задаётся базовый endpoint (можно статически или из конфига)
 * - создаётся HttpClient и единый callApi() для всех операций
 */
abstract class AbstractRequest extends BaseAbstractRequest
{
    // Пример подключения (когда сгенерируешь):
    use Traits\GeneratedMerchantInputs;

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
        return 'https://api.crypto-cash.world';
    }

    /**
     * HTTP конфиг для CryptoCash.
     *
     * ВАЖНО:
     * - publicKey всегда добавляем в payload
     * - запросы подписываются: data/signature
     */
    protected function httpConfig(): array
    {
        return [
            'baseUrl' => $this->endpointUrl('/'),

            // 60 сек на подписанные операции (как было)
            'timeout' => 60,
            'retries' => 3,
            'retryDelayMs' => 1000,

            /**
             * beforeSend позволяет превратить обычный payload в подписанный.
             * return [$path, $data, $headers]
             */
            'beforeSend' => function (string $method, string $path, array $data, array $headers) {

                // 1) добавляем publicKey в payload всегда
                $payload = array_merge([
                    'publicKey' => $this->getPublicKey(),
                ], $data);

                // 2) подписываем payload (как в старом requestSigned)
                $encodedData = $this->encodeData($payload);
                $signature   = $this->encodeSignature($this->getPrivateKey(), $encodedData);

                // 3) заменяем body на подписанный формат
                $signedBody = [
                    'data'      => $encodedData,
                    'signature' => $signature,
                ];

                return [$path, $signedBody, $headers];
            },
        ];
    }

    /**
     * base64(json) — как у тебя было.
     */
    protected function encodeData(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($json)) {
            throw new \RuntimeException('CryptoCash: не удалось сериализовать данные для подписи.');
        }

        return base64_encode($json);
    }

    /**
     * signature = base64(sha256_hex(privateKey + encodedData))
     * (оставляем твой алгоритм 1:1).
     */
    protected function encodeSignature(string $privateKey, string $encodedData): string
    {
        $hex = hash('sha256', $privateKey . $encodedData);
        return base64_encode($hex);
    }
}
