<?php

namespace iEXPackages\Courses\Services\DefaultSources;

class WhiteBitSource implements DefaultParserInterface
{
    public function getUrl(array $options = []): string
    {
        return "https://whitebit.com/api/v4/public/ticker";
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
            if (!isset($item['last_price'])) {
                continue; // Пропускаем некорректные данные
            }

            $symbol = strtoupper(str_replace('_', '', $symbol)); // Удаляем подчеркивания
            $lastPrice = $this->formatNumber($item['last_price']);

            if ($lastPrice === '0') {
                continue; // Пропускаем пустые пары
            }

            $result[$symbol] = $lastPrice;
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
