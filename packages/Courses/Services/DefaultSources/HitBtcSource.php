<?php

namespace iEXPackages\Courses\Services\DefaultSources;

class HitBtcSource implements DefaultParserInterface
{
    public function getUrl(array $options = []): string
    {
        return "https://api.hitbtc.com/api/2/public/ticker";
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

        foreach ($data as $item) {
            if (!isset($item['symbol'], $item['bid'], $item['ask'], $item['last'])) {
                continue; // Пропускаем некорректные данные
            }

            $symbol = strtoupper($item['symbol']);
            $bid = $this->formatNumber($item['bid']);
            $ask = $this->formatNumber($item['ask']);
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
