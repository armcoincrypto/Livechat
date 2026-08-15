<?php

namespace iEXPackages\Courses\Services\DefaultSources;

class RapiraSource implements DefaultParserInterface
{

    public function getUrl(array $options = []): string
    {
        return 'https://api.rapira.net/open/market/rates';
    }

    public function getParams(array $options = []): array
    {
        return [];
    }

    public function parseResponse(string $response): array
    {
        $data = json_decode($response, true);

        if (!isset($data['data']) || !is_array($data['data'])) {
            return ['error' => 'Invalid response format'];
        }

        $result = [];

        foreach ($data['data'] as $item) {
            if (!isset($item['quoteCurrency'], $item['baseCurrency'], $item['askPrice'], $item['bidPrice'])) {
                continue; // Пропускаем некорректные данные
            }

            // Приводим `quoteCurrency` и `baseCurrency` в ВЕРХНИЙ РЕГИСТР
            $quote = strtoupper($item['quoteCurrency']);
            $base = strtoupper($item['baseCurrency']);

            $key = "{$quote}{$base}";

            $result[$key] = [
                'ask' => $item['askPrice'],
                'bid' => $item['bidPrice'],
                'default' => $item['bidPrice']
            ];
        }

        return $result;
    }
}
