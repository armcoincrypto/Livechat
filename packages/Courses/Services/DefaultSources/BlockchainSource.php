<?php

namespace iEXPackages\Courses\Services\DefaultSources;

class BlockchainSource implements DefaultParserInterface
{
    public function getUrl(array $options = []): string
    {
        return 'https://blockchain.info/ticker';
    }

    public function getParams(array $options = []): array
    {
        return [];
    }

    public function parseResponse(string $response): array
    {
        $data = json_decode($response, true);

        if (!is_array($data)) {
            return ['error' => 'Invalid JSON format'];
        }

        $result = [];

        foreach ($data as $currency => $item) {
            if (!isset($item['buy'])) {
                continue; // Пропускаем некорректные данные
            }

            $quote = strtoupper($currency);
            $result["BTC{$quote}"] = $item['buy'];
        }

        return $result;
    }
}
