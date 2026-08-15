<?php

namespace iEXPackages\Courses\Services\DefaultSources;

class ExmoSource implements DefaultParserInterface
{
    public function getUrl(array $options = []): string
    {
        return "https://api.exmo.com/v1/ticker";
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

        $result = [];

        foreach ($data as $symbol => $item) {
            if (!isset($item['buy_price'], $item['sell_price'], $item['last_trade'])) {
                continue; // Пропускаем некорректные данные
            }

            $symbol = strtoupper(str_replace('_', '', $symbol)); // Удаляем подчеркивания
            $bid = $this->formatNumber($item['buy_price']);
            $ask = $this->formatNumber($item['sell_price']);
            $lastPrice = $this->formatNumber($item['last_trade']);

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
