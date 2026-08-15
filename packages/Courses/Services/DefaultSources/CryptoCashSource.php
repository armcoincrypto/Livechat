<?php
declare(strict_types=1);

namespace iEXPackages\Courses\Services\DefaultSources;

class CryptoCashSource implements DefaultParserInterface
{

    public function getUrl(array $options = []): string
    {
        return 'https://crypto-cash.world/market/rates/export/json/';
    }

    public function getParams(array $options = []): array
    {
        return [];
    }

    public function parseResponse(string $response): array
    {
        $data = json_decode($response, true);

        if (!is_array($data)) {
            return ['error' => 'Invalid JSON format'];
        }

        if (!isset($data['rates']) || !is_array($data['rates'])) {
            return ['error' => 'Missing "rates" array'];
        }

        // Priority for default field: buy > lastPrice > sell
        $priority = ['buy', 'lastPrice', 'sell'];

        $result = [];

        foreach ($data['rates'] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $give = strtoupper(trim((string)($row['give'] ?? '')));
            $get  = strtoupper(trim((string)($row['get'] ?? '')));
            if ($give === '' || $get === '') {
                continue;
            }

            $ask = $row['buy']  ?? null;   // what WE pay to buy base: price we quote to client as ask
            $bid = $row['sell'] ?? null;   // what WE get when client sells base: bid
            $def = null;

            foreach ($priority as $field) {
                if (array_key_exists($field, $row) && $row[$field] !== null && $row[$field] !== '') {
                    $def = $row[$field];
                    break;
                }
            }

            $key = "{$give}{$get}";
            $result[$key] = [
                'ask'     => $ask !== null ? $this->formatNumber($ask) : null,
                'bid'     => $bid !== null ? $this->formatNumber($bid) : null,
                'default' => $def !== null ? $this->formatNumber($def) : null,
            ];
        }

        return $result;
    }

    private function formatNumber(string|int|float $number): string
    {
        $normalized = trim((string)$number);

        if ($normalized === '' || strtolower($normalized) === 'null' || !is_numeric($normalized)) {
            return '0';
        }

        if (stripos($normalized, 'e') !== false) {
            return bcdiv($normalized, '1', 18);
        }

        return $normalized;
    }
}
