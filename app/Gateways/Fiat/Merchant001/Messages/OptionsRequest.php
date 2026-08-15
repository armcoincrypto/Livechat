<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Merchant001\Messages;

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
            'getCurrencies'   => $this->sendRequest('get', '/v2/payment-method/merchant/available', $params, 'asJson'),
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
     * Mapper для getCurrencies (Merchant001).
     *
     * Формирует список методов приёма/выплаты в формате:
     *  key   => method (например: sberbank)
     *  value => Человекочитаемое описание
     *
     * Пример label:
     *  "Сбербанк (RUB → USDT) · 100–300 000 · комиссия 10%"
     *
     * @return array<string, string>
     */
    protected function mapCurrenciesOptions(array $response): array
    {
        $options = [];

        foreach ($response as $incomeCurrency => $methods) {
            if (!is_array($methods)) {
                continue;
            }

            foreach ($methods as $methodCode => $methodData) {
                if (!is_array($methodData)) {
                    continue;
                }

                // method (ключ)
                $method = (string) ($methodData['method'] ?? $methodCode);
                if ($method === '') {
                    continue;
                }

                // Название банка / метода
                $name = (string) ($methodData['name'] ?? $method);

                // Валюты
                $income  = (string) ($methodData['incomeCurrency'] ?? $incomeCurrency);
                $outcome = (string) ($methodData['outcomeCurrency'] ?? '');

                // Берём первую variant (если есть)
                $variant = null;
                if (!empty($methodData['variants']) && is_array($methodData['variants'])) {
                    $variant = $methodData['variants'][0] ?? null;
                }

                $min = $variant['minAmount'] ?? null;
                $max = $variant['maxAmount'] ?? null;
                $fee = $variant['fee'] ?? null;

                // --- Формируем label ---
                $parts = [];

                // Название
                $parts[] = $name;

                // Валютная пара
                if ($income !== '' && $outcome !== '') {
                    $parts[] = "({$income} → {$outcome})";
                }

                // Лимиты
                if (is_numeric($min) || is_numeric($max)) {
                    $minStr = is_numeric($min) ? number_format((float)$min, 0, '.', ' ') : '—';
                    $maxStr = is_numeric($max) ? number_format((float)$max, 0, '.', ' ') : '—';
                    $parts[] = "{$minStr}–{$maxStr}";
                }

                // Комиссия
                if (is_numeric($fee)) {
                    $parts[] = "комиссия {$fee}%";
                }

                // Собираем label
                $label = implode(' · ', $parts);

                $options[$method] = $label;
            }
        }

        // Сортируем по label (алфавитно)
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        // Добавляем дефолт
        return ['' => 'Автоматически'] + $options;
    }
}
