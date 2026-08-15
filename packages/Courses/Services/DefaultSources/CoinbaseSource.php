<?php

namespace iEXPackages\Courses\Services\DefaultSources;

class CoinbaseSource implements DefaultParserInterface
{
    public function getUrl(array $options = []): string
    {
        return "https://api.coinbase.com/v2/exchange-rates";
    }

    public function getParams(array $options = []): array
    {
        return [];
    }

    public function parseResponse(string $response): array
    {
        $data = json_decode($response, true);

        if (!isset($data['data']['rates']) || !is_array($data['data']['rates'])) {
            return ['error' => 'Invalid JSON format'];
        }

        $baseCurrency = strtoupper($data['data']['currency'] ?? 'USD');
        $result = [];

        foreach ($data['data']['rates'] as $quoteCurrency => $rate) {
            $quoteCurrency = strtoupper($quoteCurrency);

            if (!empty($quoteCurrency) && is_numeric($rate)) {
                $result["{$baseCurrency}{$quoteCurrency}"] = number_format((float)$rate, 6, '.', '');
            }
        }

        return $result;
    }
}
