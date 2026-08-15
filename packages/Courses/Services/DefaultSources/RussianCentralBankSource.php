<?php

namespace iEXPackages\Courses\Services\DefaultSources;

class RussianCentralBankSource implements DefaultParserInterface
{
    public function getUrl(array $options = []): string
    {
        return "https://www.cbr.ru/scripts/XML_daily.asp";
    }

    public function getParams(array $options = []): array
    {
        return []; // Нет параметров
    }

    public function parseResponse(string $response): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);

        if (!$xml) {
            return ['error' => 'Invalid XML format'];
        }

        $result = [];

        // Проходим по всем валютам
        foreach ($xml->Valute as $valute) {
            $charCode = strtoupper((string)$valute->CharCode);
            $value = (float)str_replace(',', '.', $valute->Value);
            $nominal = (int)$valute->Nominal;

            // ✅ Делим Value на Nominal, чтобы получить цену за 1 единицу
            $result[$charCode  . 'RUB'] = $nominal > 0 ? $value / $nominal : 0;
        }

        return $result;
    }
}
