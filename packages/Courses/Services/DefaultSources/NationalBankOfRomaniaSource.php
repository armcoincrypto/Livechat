<?php

namespace iEXPackages\Courses\Services\DefaultSources;

class NationalBankOfRomaniaSource implements DefaultParserInterface
{
    public function getUrl(array $options = []): string
    {
        return "https://www.bnr.ro/nbrfxrates.xml";
    }

    public function getParams(array $options = []): array
    {
        return [];
    }

    public function parseResponse(string $response): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);

        if (!$xml) {
            return ['error' => 'Invalid XML format'];
        }

        $result = [];

        foreach ($xml->Body->Cube->Rate as $rate) {
            $currency = (string) $rate['currency'];
            $value = (string) $rate;
            $multiplier = isset($rate['multiplier']) ? (string) $rate['multiplier'] : '1';

            if (!empty($currency) && !empty($value) && is_numeric($value) && is_numeric($multiplier)) {
                $adjustedRate = bcdiv($value, $multiplier, 8); // Делим rate на multiplier
                $result["RON{$currency}"] = $this->formatNumber($adjustedRate);
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
        if (stripos($number, 'E') !== false) {
            return bcdiv($number, '1', 8); // Используем bcdiv для высокой точности (18 знаков)
        }

        return $number; // Если число нормальное, не трогаем
    }
}
