<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Goldex\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use InvalidArgumentException;

final class OptionsRequest extends AbstractRequest
{
    public function getData(): array
    {
        $method = (string) $this->getParameter('method', '');
        $group  = (string) $this->getParameter('group', 'merchant'); // merchant|pay
        $params = (array) $this->getParameter('params', []);

        if ($method === '') {
            throw new InvalidArgumentException('OptionsRequest: parameter "method" is required.');
        }

        return [
            'method' => $method,
            'group'  => $group,
            'params' => $params,
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
            'getExchangeRate' => $this->sendRequest('get', '/api/v3/exchange_rate/', $params, 'asJson'),
            default => throw new InvalidArgumentException("OptionsRequest: unsupported method [{$method}]"),
        };
    }

    /**
     * @param 'merchant'|'pay' $group
     * @return array<string,string>
     */
    protected function mapOptions(string $method, array $result, string $group): array
    {
        return match ($method) {
            'getExchangeRate' => $this->mapExchangeRateOptions($result),
            default           => [],
        };
    }

    /**
     * Mapper для getExchangeRate.
     *
     * Старое поведение:
     * - response.status === 'Ok'
     * - берём response.data (array)
     * - reject blocked == 1
     * - key   => id
     * - value => "[id, currency.type] - currency.name (Min: x, Max: y)"
     * - сортировка по ключу (id)
     *
     * @return array<string,string>
     */
    protected function mapExchangeRateOptions(array $response): array
    {
        $status = (string) ($response['status'] ?? '');
        $data   = $response['data'] ?? null;

        if ($status !== 'Ok' || !is_array($data) || $data === []) {
            return [];
        }

        $options = [];

        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }

            // blocked
            $blocked = $row['blocked'] ?? null;
            if (is_numeric($blocked) && (int) $blocked === 1) {
                continue;
            }

            // id
            $id = $row['id'] ?? null;
            if (!is_numeric($id)) {
                continue;
            }
            $idStr = (string) (int) $id;

            // currency fields
            $currency = $row['currency'] ?? [];
            $currencyType = is_array($currency) ? (string) ($currency['type'] ?? '') : '';
            $currencyName = is_array($currency) ? (string) ($currency['name'] ?? '') : '';

            // limits
            $min = $row['minValueFIAT'] ?? null;
            $max = $row['maxValueFIAT'] ?? null;

            $minStr = $this->formatMoneyMaybe($min);
            $maxStr = $this->formatMoneyMaybe($max);

            $label = sprintf(
                '[%s%s] - %s (Мин: %s, Макс: %s)',
                $idStr,
                $currencyType !== '' ? ', ' . $currencyType : '',
                $currencyName !== '' ? $currencyName : '—',
                $minStr,
                $maxStr
            );

            $options[$idStr] = $label;
        }

        // сортировка по ID как в старом (по ключу)
        uksort($options, static function (string $a, string $b): int {
            return (int) $a <=> (int) $b;
        });

        return $options;
    }

    private function formatMoneyMaybe(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_numeric($value)) {
            // если целое — без копеек, иначе 2 знака
            $f = (float) $value;
            $isInt = abs($f - round($f)) < 0.0000001;

            return number_format($f, $isInt ? 0 : 2, '.', ' ');
        }

        // если пришло строкой (например "1000.00") — оставим как есть, но подчистим пробелы
        $s = trim((string) $value);
        return $s !== '' ? $s : '—';
    }
}
