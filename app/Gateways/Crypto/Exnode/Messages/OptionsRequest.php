<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Exnode\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use InvalidArgumentException;

final class OptionsRequest extends AbstractRequest
{
    /**
     * Входные параметры:
     *  - method: string (например getCurrencies)
     *  - params: array (опционально)
     */
    public function getData(): array
    {
        $method = trim((string) $this->getParameter('method', ''));

        if ($method === '') {
            throw new InvalidArgumentException(
                'OptionsRequest: parameter "method" is required.'
            );
        }

        return [
            'method' => $method,
            'params' => (array) $this->getParameter('params', []),
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $method = (string) ($data['method'] ?? '');
        $params = (array) ($data['params'] ?? []);

        // 1) вызов API
        $rawResponse = $this->callOptionsApi($method, $params);

        // 2) маппинг в id => value
        $options = $this->mapOptions($method, $rawResponse);

        return $this->response = new OptionsResponse(
            request: $this,
            data:    $options,
            query:   $data
        );
    }

    /**
     * Вызов конкретного API-метода Exnode.
     */
    protected function callOptionsApi(string $method, array $params = []): array
    {
        return match ($method) {
            // Старый Exnode API
            // GET /user/token/fetch
            // => ['tokens' => ['USDTTRC','BTC',...]]
            'getCurrencies' => (array) $this->sendRequest(
                method: 'get',
                path:   '/user/token/fetch',
                data:   $params,
                format: 'asJson'
            ),

            default => throw new InvalidArgumentException(
                "OptionsRequest: unsupported method [{$method}]"
            ),
        };
    }

    /**
     * Маппинг raw API -> options для UI.
     */
    protected function mapOptions(string $method, array $raw): array
    {
        return match ($method) {
            'getCurrencies' => $this->mapCurrenciesOptions($raw),
            default         => [],
        };
    }

    /**
     * Exnode mapper для getCurrencies.
     *
     * Старый ответ Exnode:
     * [
     *   'tokens' => ['USDTTRC','BTC','ETH',...]
     * ]
     *
     * Новый UI-формат:
     * [
     *   '' => 'Автоматически',
     *   'USDTTRC' => 'USDTTRC',
     *   'BTC'     => 'BTC',
     * ]
     */
    protected function mapCurrenciesOptions(array $response): array
    {
        $tokens = $response['tokens'] ?? null;

        if (!is_array($tokens) || $tokens === []) {
            return ['' => 'Автоматически'];
        }

        $options = [];

        foreach ($tokens as $token) {
            if (!is_string($token)) {
                continue;
            }

            $token = trim($token);
            if ($token === '') {
                continue;
            }

            $options[$token] = $token;
        }

        if ($options === []) {
            return ['' => 'Автоматически'];
        }

        ksort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return ['' => 'Автоматически'] + $options;
    }
}
