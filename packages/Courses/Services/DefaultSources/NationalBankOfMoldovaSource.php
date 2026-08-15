<?php

namespace iEXPackages\Courses\Services\DefaultSources;

class NationalBankOfMoldovaSource implements DefaultParserInterface
{
    public function getUrl(array $options = []): string
    {
        return "https://www.bnm.md/ru/official_exchange_rates";
    }


    public function getParams(array $options = []): array
    {
        $date = date('d.m.Y');
        return [
            'get_xml' => 1,
            'date' => $date
        ];
    }

    public function parseResponse(string $response): array
    {

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);

        if (!$xml || !isset($xml->Valute)) {
            return ['error' => 'Invalid XML format'];
        }

        $result = [];

        foreach ($xml->Valute as $currency) {
            $charCode = (string)$currency->CharCode;
            $value = str_replace(',', '.', (string)$currency->Value);
            $nominal = isset($currency->Nominal) ? (float)$currency->Nominal : 1;

            if (!empty($charCode) && is_numeric($value) && $nominal > 0) {
                $adjustedRate = (float)$value / $nominal;
                $result["MDL{$charCode}"] = number_format($adjustedRate, 6, '.', '');
            }
        }

        return $result;
    }
}
