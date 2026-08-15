<?php

namespace iEXPackages\Courses\Services\DefaultSources;

class HeleketSource implements DefaultParserInterface
{

    public function getUrl(array $options = []): string
    {
        return 'https://api.heleket.com/v2/exchange-rate/list';
    }

    public function getParams(array $options = []): array
    {
        return [];
    }

    public function parseResponse(string $response): array
    {
        $data = json_decode($response, true);

        if (!isset($data['result']) || !is_array($data['result'])) {
            return ['error' => 'Invalid JSON format'];
        }

        $result = [];

        foreach ($data['result'] as $baseCurrency => $quotes) {
            if (!is_array($quotes)) {
                continue;
            }
            foreach ($quotes as $quoteCurrency => $rate) {
                $key = strtoupper("{$baseCurrency}{$quoteCurrency}");

                $result[$key] =  $this->formatNumber($rate);
            }
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
