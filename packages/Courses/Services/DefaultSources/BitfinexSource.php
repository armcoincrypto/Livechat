<?php

namespace iEXPackages\Courses\Services\DefaultSources;

class BitfinexSource implements DefaultParserInterface
{
    public function getUrl(array $options = []): string
    {
        return "https://api-pub.bitfinex.com/v2/tickers";
    }

    public function getParams(array $options = []): array
    {
        return [
            'symbols' => 'ALL'
        ];
    }

    public function parseResponse(string $response): array
    {
        $data = json_decode($response, true);

        if (!is_array($data)) {
            return ['error' => 'Invalid JSON format'];
        }

        $result = [];

        foreach ($data as $item) {
            if (is_array($item) && count($item) > 7 && str_starts_with($item[0], 't')) {
                $symbol = substr($item[0], 1);

                $bid = $this->formatNumber($item[1]);
                $ask = $this->formatNumber($item[3]);
                $lastPrice = $this->formatNumber($item[7]);

                if ($bid === '0' && $ask === '0' && $lastPrice === '0') {
                    continue; // Пропускаем пустые пары
                }

                $result[$symbol] = [
                    'bid' => $bid,
                    'ask' => $ask,
                    'default' => $lastPrice
                ];
            }
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
