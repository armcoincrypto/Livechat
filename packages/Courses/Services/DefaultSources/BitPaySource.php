<?php

namespace iEXPackages\Courses\Services\DefaultSources;

class BitPaySource implements DefaultParserInterface
{
    public function getUrl(array $options = []): string
    {
        return "https://bitpay.com/rates";
    }

    public function getParams(array $options = []): array
    {
        return [];
    }

    public function parseResponse(string $response): array
    {
        $data = json_decode($response, true);

        if (!isset($data['data']) || !is_array($data['data'])) {
            return ['error' => 'Invalid JSON format'];
        }

        $result = [];

        foreach ($data['data'] as $item) {
            if (!isset($item['code'], $item['rate'])) {
                continue; // Пропускаем некорректные данные
            }

            $currency = strtoupper($item['code']);
            $rate = $this->formatNumber($item['rate']);

            $result["BTC{$currency}"] = $rate;
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
