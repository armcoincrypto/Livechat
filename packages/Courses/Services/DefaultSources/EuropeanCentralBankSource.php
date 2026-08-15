<?php

namespace iEXPackages\Courses\Services\DefaultSources;

class EuropeanCentralBankSource implements DefaultParserInterface
{
    public function getUrl(array $options = []): string
    {
        return "https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml";
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

        foreach ($xml->Cube->Cube->Cube as $cube) {
            $currency = (string) $cube['currency'];
            $rate = (string) $cube['rate'];

            if (!empty($currency) && !empty($rate)) {
                $result["EUR{$currency}"] = $this->formatNumber($rate);
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
            return bcdiv($number, '1', 18); // Используем bcdiv для высокой точности (18 знаков)
        }

        return $number; // Если число нормальное, не трогаем
    }
}
