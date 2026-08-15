<?php

namespace iEXPackages\Courses\Services\DefaultSources;

class BitMartSource implements DefaultParserInterface
{
    public function getUrl(array $options = []): string
    {
        return "https://api-cloud.bitmart.com/spot/v1/ticker";
    }

    public function getParams(array $options = []): array
    {
        return [];
    }

    public function parseResponse(string $response): array
    {
        $data = json_decode($response, true);

        if (!isset($data['data']['tickers']) || !is_array($data['data']['tickers'])) {
            return ['error' => 'Invalid JSON format'];
        }

        $result = [];

        foreach ($data['data']['tickers'] as $item) {
            if (!isset($item['symbol'], $item['best_bid'], $item['best_ask'], $item['last_price'])) {
                continue; // Пропускаем некорректные данные
            }

            $symbol = strtoupper(str_replace('_', '', $item['symbol'])); // Убираем подчеркивания
            $bid = $this->formatNumber($item['best_bid']);
            $ask = $this->formatNumber($item['best_ask']);
            $lastPrice = $this->formatNumber($item['last_price']);

            if ($bid === '0' && $ask === '0' && $lastPrice === '0') {
                continue; // Пропускаем пустые пары
            }

            $result[$symbol] = [
                'bid' => $bid,
                'ask' => $ask,
                'default' => $lastPrice
            ];
        }

        return $result;
    }

    private function formatNumber(string|float $number): string
    {
        $number = trim((string)$number);

        if ($number === '' || !is_numeric($number)) {
            return '0';
        }

        // Обработка экспоненциальной нотации
        if (stripos($number, 'E') !== false) {
            $number = number_format((float)$number, 18, '.', '');
        }

        return rtrim(rtrim(bcdiv($number, '1', 18), '0'), '.');
    }
}
