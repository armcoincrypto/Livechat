<?php

namespace iEXPackages\Courses\Services\DefaultSources;


class NationalBankOfKyrgyzstanSource implements DefaultParserInterface
{
    public function getUrl(array $options = []): string
    {
        return "https://www.nbkr.kg/XML/daily.xml";
    }

    public function getParams(array $options = []): array
    {
        return [];
    }

    public function parseResponse(string $response): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);

        if (!$xml || !isset($xml->Currency)) {
            return ['error' => 'Invalid XML format'];
        }

        $result = [];

        foreach ($xml->Currency as $currency) {
            $charCode = (string) $currency['ISOCode'];
            $value = str_replace(',', '.', (string) $currency->Value); // Исправляем запятую
            $nominal = isset($currency->Nominal) ? str_replace(',', '.', (string) $currency->Nominal) : '1';

            if (!empty($charCode) && !empty($value) && is_numeric($value) && is_numeric($nominal)) {
                $adjustedRate = (float) $value / (float) $nominal; // Делим rate на nominal без bcmath
                $result["KGS{$charCode}"] = $this->formatNumber($adjustedRate);
            }
        }

        return $result;
    }

    private function formatNumber(float $number): string
    {
        return number_format($number, 6, '.', ''); // Округляем до 6 знаков после запятой
    }
}
