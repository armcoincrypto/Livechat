<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Heleket\Messages;

use iEXPackages\Payments\Core\Engine\AbstractRequest as BaseAbstractRequest;

/**
 * Базовый Request для шлюза Heleket.
 *
 * Здесь:
 * - подключаются Generated*Inputs (позже генератором)
 * - задаётся базовый endpoint (можно статически или из конфига)
 * - создаётся HttpClient и единый callApi() для всех операций
 */
abstract class AbstractRequest extends BaseAbstractRequest
{
    use Traits\GeneratedMerchantInputs,
        Traits\GeneratedPayInputs;

    protected function endpointStaticBaseUrl(): string
    {
        return 'https://api.heleket.com';
    }

    /**
     * HTTP-настройки шлюза (только различия).
     * Отправка/логирование/ретраи реализованы в ядре (GatewayHttpClient + sendRequest()).
     */
    protected function httpConfig(): array
    {
        return [
            // baseUrl для Http::baseUrl должен быть абсолютным URL
            'baseUrl' => $this->endpointUrl('/'),

            'timeout'      => 15,
            'retries'      => 3,
            'retryDelayMs' => 1000,

            /**
             * beforeSend:
             * - формируем sign
             * - добавляем headers merchant + sign
             * return [$path, $data, $headers]
             */
            'beforeSend' => function (string $method, string $path, array $data, array $headers) {

                $path = $this->normalizeApiPath($path);


                $sign = $this->generateHeleketSign($path, $data);

                $headers['merchant'] = $this->getConnectionValue('getMerchantId');
                $headers['sign']     = $sign;

                return [$path, $data, $headers];
            },

            /**
             * afterResponse:
             * - старая логика: state === 0 и есть result => ok
             * - иначе exception
             */
            'afterResponse' => function (array $json, int $httpStatus) {

                // ожидаем структуру как в старом
                if (isset($json['state'], $json['result']) && (int) $json['state'] === 0) {
                    return $json;
                }

                // если провайдер вернул message/error — берём
                $msg = (string)($json['message'] ?? $json['error'] ?? 'Heleket API error');

                throw new \Exception($msg . ': ' . json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            },
        ];
    }

    /**
     * Генерация подписи запроса Heleket.
     *
     * Алгоритм (как в старой версии):
     *   sign = md5( base64_encode(json(params)) . key )
     *
     * key:
     *  - для payout endpoints: payout_key
     *  - иначе: secret_key
     */
    protected function generateHeleketSign(string $path, array $params): string
    {
        $json = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($json)) {
            $json = '{}';
        }

        $key = $this->isPayoutPath($path)
            ? $this->getPayPayoutKey()
            : $this->getconnectionValue('getSecretKey');

        return md5(base64_encode($json) . $key);
    }

    /**
     * Определяем payout endpoints (как в старой версии).
     */
    protected function isPayoutPath(string $path): bool
    {
        $path = $this->normalizeApiPath($path);

        return in_array($path, ['/v1/payout', '/v1/payout/info'], true);
    }

    /**
     * Нормализация пути: гарантируем ведущий slash и отсутствие baseUrl.
     */
    protected function normalizeApiPath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '/';
        }

        // если вдруг сюда прилетел полный url — возьмём только path
        if (str_contains($path, '://')) {
            $parsedPath = (string) (parse_url($path, PHP_URL_PATH) ?? '');
            $path = $parsedPath !== '' ? $parsedPath : '/';
        }

        return str_starts_with($path, '/') ? $path : '/' . $path;
    }
}
