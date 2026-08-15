<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\AlfabitPay\Messages;

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
            'getExchangeRate' => $this->sendRequest('get', '/api/v1/integration/assets/exchange-rate', $params, 'asJson'),
            'getCurrencies'   => $this->sendRequest('get', '/api/v1/integration/assets/currencies', $params, 'asJson'),
            default => throw new InvalidArgumentException("OptionsRequest: unsupported method [{$method}]"),
        };
    }

    protected function mapOptions(string $method, array $result): array
    {
        return match ($method) {
            'getExchangeRate' => $this->mapExchangeRateOptions($result),
            'getCurrencies'   => $this->mapCurrenciesOptions($result),
            default           => [],
        };
    }

    /**
     * Mapper для getExchangeRate.
     *
     * @return array<string, string>
     */
    protected function mapExchangeRateOptions(array $response): array
    {
        $message = (string) ($response['message'] ?? '');
        $data    = $response['data'] ?? null;

        if ($message !== 'ok' || !is_array($data) || $data === []) {
            return ['' => 'Ничего не выбрано'];
        }

        $options = [];

        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }

            $isTurnRate = $item['isTurnRate'] ?? null;
            if (is_numeric($isTurnRate) && (int) $isTurnRate === 1) {
                continue;
            }

            $code = $item['toAssetCode'] ?? null;
            if (!is_string($code) || $code === '') {
                continue;
            }

            $options[$code] = $code;
        }

        // '' => "Ничего не выбрано" + сортировка по ключу
        $empty = 'Ничего не выбрано';
        ksort($options, SORT_NATURAL);

        return ['' => $empty] + $options;
    }

    /**
     * Mapper для getCurrencies (как в старом коде).
     *
     * Старое:
     * - reject isInternal == 1
     * - map: id=publicCode, name="[publicCode] - [currencyType] - publicName"
     * - pluck name,id
     * - put '' => 'Автоматически'
     * - sort by key
     *
     * @return array<string, string>
     */
    protected function mapCurrenciesOptions(array $response): array
    {
        $message = (string) ($response['message'] ?? '');
        $data    = $response['data'] ?? null;

        // если нет нормального ответа — всё равно вернём дефолт
        if ($message !== 'ok' || !is_array($data) || $data === []) {
            return ['' => 'Автоматически'];
        }

        $options = [];

        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }

            // reject isInternal == 1
            $isInternal = $item['isInternal'] ?? null;
            if (is_numeric($isInternal) && (int) $isInternal === 1) {
                continue;
            }

            $publicCode   = $item['publicCode'] ?? null;
            $currencyType = $item['currencyType'] ?? '';
            $publicName   = $item['publicName'] ?? '';

            if (!is_string($publicCode) || $publicCode === '') {
                continue;
            }

            $label = '[' . $publicCode . '] - [' . (string)$currencyType . '] - ' . (string)$publicName;
            $options[$publicCode] = $label;
        }

        // '' => "Автоматически" + сортировка по ключу
        ksort($options, SORT_NATURAL);

        return ['' => 'Автоматически'] + $options;
    }
}
