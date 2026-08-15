<?php

namespace iEXPackages\Courses\Services\DefaultSources;

class NationalBankOfKazakhstanSource implements DefaultParserInterface
{
    public function getUrl(array $options = []): string
    {
        return "https://www.nationalbank.kz/rss/rates_all.xml";
    }

    public function getParams(array $options = []): array
    {
        return [];
    }

    public function parseResponse(string $response): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);

        if (!$xml || !isset($xml->channel->item)) {
            return ['error' => 'Invalid XML format'];
        }

        $result = [];

        foreach ($xml->channel->item as $item) {
            $currency = (string) $item->title;
            $value = str_replace(',', '.', (string) $item->description);
            $nominal = isset($item->quant) ? (float)$item->quant : 1;

            if (!empty($currency) && is_numeric($value) && $nominal > 0) {
                $adjustedRate = (float)$value / $nominal;
                $result["KZT{$currency}"] = number_format($adjustedRate, 6, '.', '');
            }
        }

        return $result;
    }
}
