<?php

namespace iEXPackages\Courses\Services\DefaultSources;

class UzbekistanCentralBankSource implements DefaultParserInterface
{
    public function getUrl(array $options = []): string
    {
        return "https://cbu.uz/ru/arkhiv-kursov-valyut/xml/";
    }

    public function getParams(array $options = []): array
    {
        return [];
    }

    public function parseResponse(string $response): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);

        if (!$xml || !isset($xml->CcyNtry)) {
            return ['error' => 'Invalid XML format'];
        }

        $result = [];

        foreach ($xml->CcyNtry as $currency) {
            $charCode = (string) $currency->Ccy;
            $value = str_replace(',', '.', (string) $currency->Rate);
            $nominal = isset($currency->Nominal) ? str_replace(',', '.', (string) $currency->Nominal) : '1';

            if (!empty($charCode) && is_numeric($value) && is_numeric($nominal)) {
                $adjustedRate = (float) $value / (float) $nominal;
                $result["UZS{$charCode}"] = number_format($adjustedRate, 6, '.', '');
            }
        }

        return $result;
    }
}
