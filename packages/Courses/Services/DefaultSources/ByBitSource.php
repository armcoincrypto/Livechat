<?php

namespace iEXPackages\Courses\Services\DefaultSources;

class ByBitSource implements DefaultParserInterface
{

    public function getUrl(array $options = []): string
    {
        return 'https://api.bybit.com/v5/market/tickers';
    }

    public function getParams(array $options = []): array
    {
        return [
            'category' => 'spot',
        ];
    }

    public function parseResponse(string $response): array
    {
        $data = json_decode($response, true);

        if (!isset($data['result']['list']) || !is_array($data['result']['list'])) {
            return ['error' => 'Invalid JSON format'];
        }

        $result = [];

        foreach ($data['result']['list'] as $item) {
            if (!isset($item['symbol'], $item['lastPrice'])) {
                continue; // Пропускаем некорректные данные
            }

            $price = $this->formatNumber($item['lastPrice']); // Используем `bcdiv()` для точного числа

            // Пропускаем некорректные цены
            if ($price === '0' || $price === null || !is_numeric($price) || bccomp($price, '0', 18) === 0) {
                continue;
            }


            $symbol = strtoupper($item['symbol']); // Приводим в ВЕРХНИЙ РЕГИСТР
            $result[$symbol] = $price;
        }

        return $result;
    }

    private function formatNumber(string $number): string
    {
        // Убираем пробелы и ненужные символы
        $number = trim($number);

        // Проверяем, является ли число корректным
        if ($number === '' || strtolower($number) === 'null' || !is_numeric($number)) {
            return '0';
        }

        // Если число в экспоненциальной нотации, преобразуем его через bcdiv
        if (strpos($number, 'E') !== false || strpos($number, 'e') !== false) {
            return bcdiv($number, '1', 18); // Используем bcdiv для высокой точности (18 знаков)
        }

        return $number; // Если число нормальное, не трогаем
    }
}
