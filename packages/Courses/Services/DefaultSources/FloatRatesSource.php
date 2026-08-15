<?php

namespace iEXPackages\Courses\Services\DefaultSources;

class FloatRatesSource implements DefaultParserInterface
{
    public function getUrl(array $options = []): string
    {
        return "https://www.floatrates.com/daily/usd.xml"; // Возвращаем 1 URL по умолчанию
    }

    public function getParams(array $options = []): array
    {
        return [];
    }

    public function parseResponse(string $response): array
    {
        $baseCurrencies = ["usd", "eur", "uah", "rub", "cny"];
        $responses = ["usd" => $response]; // Первичный ответ

        // Загружаем остальные курсы валют
        foreach ($baseCurrencies as $currency) {
            if ($currency === "usd") {
                continue; // USD уже загружен
            }
            $responses[$currency] = file_get_contents("https://www.floatrates.com/daily/{$currency}.xml");
        }

        return $this->processResponses($responses);
    }

    private function processResponses(array $responses): array
    {
        $result = [];

        foreach ($responses as $baseCurrency => $response) {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);

            if (!$xml || !isset($xml->item)) {
                continue;
            }

            foreach ($xml->item as $item) {
                $quoteCurrency = strtoupper((string) $item->targetCurrency);
                $rate = str_replace(',', '.', (string) $item->exchangeRate);

                if (!empty($quoteCurrency) && is_numeric($rate)) {
                    $result[strtoupper($baseCurrency) . $quoteCurrency] = number_format((float)$rate, 6, '.', '');
                }
            }
        }

        return $result;
    }
}
