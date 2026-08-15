<?php

namespace iEXPackages\Courses\Services\DefaultSources;

class KuCoinSource implements DefaultParserInterface
{
    public function getUrl(array $options = []): string
    {
        return "https://openapi-v2.kucoin.com/api/v1/market/allTickers";
    }

    public function getParams(array $options = []): array
    {
        return [];
    }

    public function parseResponse(string $response): array
    {
        $data = json_decode($response, true);

        if (!isset($data['data']['ticker']) || !is_array($data['data']['ticker'])) {
            return ['error' => 'Invalid JSON format'];
        }

        $result = [];

        foreach ($data['data']['ticker'] as $item) {
            if (!isset($item['symbol'], $item['buy'], $item['sell'], $item['last'])) {
                continue; // Пропускаем некорректные данные
            }

            $symbol = strtoupper(str_replace('-', '', $item['symbol'])); // Удаляем тире из символа
            $bid = $this->formatNumber($item['buy']);
            $ask = $this->formatNumber($item['sell']);
            $lastPrice = $this->formatNumber($item['last']);

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

        // Используем bcdiv, чтобы привести к высокой точности и избежать лишних нулей
        return rtrim(rtrim(bcdiv($number, '1', 18), '0'), '.');
    }
}
