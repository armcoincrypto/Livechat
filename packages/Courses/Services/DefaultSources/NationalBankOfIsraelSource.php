<?php

namespace iEXPackages\Courses\Services\DefaultSources;

class NationalBankOfIsraelSource implements DefaultParserInterface
{
    public function getUrl(array $options = []): string
    {
        return "https://boi.org.il/PublicApi/GetExchangeRates";
    }

    public function getParams(array $options = []): array
    {
        return [];
    }

    public function parseResponse(string $response): array
    {
        $data = json_decode($response, true);

        if (!isset($data['exchangeRates']) || !is_array($data['exchangeRates'])) {
            return ['error' => 'Invalid JSON format'];
        }

        $result = [];

        foreach ($data['exchangeRates'] as $item) {
            if (!isset($item['key'], $item['currentExchangeRate'], $item['unit'])) {
                continue; // Пропускаем некорректные данные
            }

            $charCode = strtoupper($item['key']);
            $value = str_replace(',', '.', (string) $item['currentExchangeRate']);
            $nominal = isset($item['unit']) ? (float)$item['unit'] : 1;

            if (!empty($charCode) && is_numeric($value) && is_numeric($nominal) && $nominal > 0) {
                $adjustedRate = (float) $value / (float) $nominal;
                $result["ILS{$charCode}"] = number_format($adjustedRate, 6, '.', '');
            }
        }

        return $result;
    }
}
