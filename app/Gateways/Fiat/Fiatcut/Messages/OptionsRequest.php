<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Fiatcut\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use InvalidArgumentException;

final class OptionsRequest extends AbstractRequest
{
    public function getData(): array
    {
        $method = (string) $this->getParameter('method', '');
        if ($method === '') {
            throw new InvalidArgumentException('OptionsRequest: parameter "method" is required.');
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

        $result  = $this->callOptionsApi($method, $params);
        $options = $this->mapOptions($method, $result);

        return $this->response = new OptionsResponse(
            $this,
            $options,
            $data
        );
    }

    protected function callOptionsApi(string $method, array $params = []): array
    {
        return match ($method) {
            'getCurrencies'   => $this->sendRequest('get', '/api/payment-gateways', $params, 'asJson'),
            default => throw new InvalidArgumentException("OptionsRequest: unsupported method [{$method}]"),
        };
    }

    protected function mapOptions(string $method, array $result): array
    {
        return match ($method) {
            'getCurrencies'   => $this->mapCurrenciesOptions($result),
            default           => [],
        };
    }


    /**
     * Преобразует ответ API /api/payment-gateways в options-массив для select: [code => label].
     *
     * Ожидаемый формат:
     *  [
     *    'success' => true|1,
     *    'data' => [
     *      [
     *        'code' => 'CARD',
     *        'name' => '...',
     *        'currency' => 'RUB',
     *        'detail_types' => ['Card', 'SBP'] // или строка
     *      ],
     *      ...
     *    ]
     *  ]
     *
     * @return array<string, string>
     */
    protected function mapCurrenciesOptions(array $response): array
    {
        $success = $response['success'] ?? null;
        $data    = $response['data'] ?? null;

        // если нет нормального ответа — всё равно вернём дефолт
        if (empty($success) || !is_array($data) || $data === []) {
            return ['' => 'Автоматически'];
        }

        $options = [];

        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }

            $code = $item['code'] ?? null;
            if (!is_string($code)) {
                continue;
            }

            $code = trim($code);
            if ($code === '') {
                continue;
            }

            $name     = trim((string) ($item['name'] ?? ''));
            $currency = trim((string) ($item['currency'] ?? ''));

            $detailTypes = $item['detail_types'] ?? [];
            if (is_array($detailTypes)) {
                $detailTypesString = implode(', ', array_map('strval', $detailTypes));
            } else {
                $detailTypesString = trim((string) $detailTypes);
            }

            // Собираем label как в старой версии: name, code, currency, detailTypesString
            $labelParts = [
                $name,
                $code,
                $currency,
                $detailTypesString,
            ];

            // убираем пустые элементы, чтобы не было ", ,"
            $labelParts = array_values(array_filter($labelParts, static fn ($v) => trim((string)$v) !== ''));

            $label = implode(', ', $labelParts);

            // если вдруг всё пусто — хотя бы code
            if ($label === '') {
                $label = $code;
            }

            $options[$code] = $label;
        }

        // Сортировка по ключу (code)
        ksort($options, SORT_NATURAL);

        // Добавляем дефолт первым пунктом
        return ['' => 'Автоматически'] + $options;
    }
}
