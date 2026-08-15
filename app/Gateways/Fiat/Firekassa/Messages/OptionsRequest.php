<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Firekassa\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use InvalidArgumentException;

final class OptionsRequest extends AbstractRequest
{
    public function getData(): array
    {
        $method = (string) ($data['method'] ?? '');
        $group  = (string) ($data['group'] ?? 'merchant');
        $params = (array) ($data['params'] ?? []);

        $method = (string) $this->getParameter('method', '');
        $group = (string) $this->getParameter('group', '');
        if ($method === '') {
            throw new InvalidArgumentException('OptionsRequest: parameter "method" is required.');
        }

        return [
            'method' => $method,
            'group'  => $group,
            'params' => (array) $this->getParameter('params', []),
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $method = (string) ($data['method'] ?? '');
        $group  = (string) ($data['group'] ?? 'merchant');
        $params = (array) ($data['params'] ?? []);

        $result  = $this->callOptionsApi($method, $params);
        $options = $this->mapOptions($method, $result, $group);

        return $this->response = new OptionsResponse(
            $this,
            $options,
            $data
        );
    }

    protected function callOptionsApi(string $method, array $params = []): array
    {
        return match ($method) {
            'getCardMethods'   => $this->sendRequest('get', '/api/v2/payment-methods', $params, 'asJson'),
            default => throw new InvalidArgumentException("OptionsRequest: unsupported method [{$method}]"),
        };
    }

    protected function mapOptions(string $method, array $result, string $group): array
    {
        return match ($method) {
            'getCardMethods' => $this->mapCurrenciesOptions($result, $group),
            default          => [],
        };
    }


    /**
     * Mapper для getCardMethods (Merchant001 / Firekassa и т.п.).
     *
     * Формат:
     *   key   => site_account
     *   value => "Название · приём · выплата"
     *
     * @return array<string, string>
     */
    /**
     * Mapper для getCardMethods (Firekassa / Merchant001).
     *
     * key   => data (например: "sbp|sber")
     * value => "СБП (RUB) · приём · 100–1 000 000 · комиссия 6 %"
     *
     * @param 'merchant'|'pay' $group
     * @return array<string,string>
     */
    protected function mapCurrenciesOptions(array $response, string $group): array
    {
        $options = [];

        foreach ($response as $item) {
            if (!is_array($item)) {
                continue;
            }

            $dataKey = (string) ($item['data'] ?? '');
            if ($dataKey === '') {
                continue;
            }

            // фильтр по направлению
            if ($group === 'merchant' && empty($item['in'])) {
                continue;
            }
            if ($group === 'pay' && empty($item['out'])) {
                continue;
            }

            // исключаем лишнее (как ты просил ранее)
            if (
                str_starts_with($dataKey, 'account|') ||
                str_starts_with($dataKey, 'ecom|') ||
                str_starts_with($dataKey, 'binance-')
            ) {
                continue;
            }

            $name = (string) ($item['name'] ?? $dataKey);

            $parts = [];
            $parts[] = $name;

            // режим
            $parts[] = $group === 'merchant' ? 'приём' : 'выплата';

            // лимиты
            if (!empty($item['limits']) && is_array($item['limits'])) {
                $min = $item['limits']['min'] ?? null;
                $max = $item['limits']['max'] ?? null;

                if (is_numeric($min) || is_numeric($max)) {
                    $minStr = is_numeric($min) ? number_format((float)$min, 0, '.', ' ') : '—';
                    $maxStr = is_numeric($max) ? number_format((float)$max, 0, '.', ' ') : '—';
                    $parts[] = "{$minStr}–{$maxStr}";
                }
            }

            // комиссия
            if (!empty($item['commission']['text'])) {
                $parts[] = 'комиссия ' . trim((string)$item['commission']['text']);
            }

            $options[$dataKey] = implode(' · ', $parts);
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }
}
