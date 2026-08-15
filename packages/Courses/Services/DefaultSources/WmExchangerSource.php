<?php

namespace iEXPackages\Courses\Services\DefaultSources;


class WmExchangerSource implements DefaultParserInterface
{
    public function getUrl(array $options = []): string
    {
        return "https://wm.exchanger.ru/asp/JSONbestRates.asp";
    }

    public function getParams(array $options = []): array
    {
        return [];
    }

    public function parseResponse(string $response): array
    {
        $data = json_decode($response, true);

        if (!isset($data['response']) || !is_array($data['response'])) {
            return ['error' => 'Invalid JSON format'];
        }

        $result = [];

        foreach ($data['response'] as $item) {
            if (!isset($item['Direct'], $item['BaseRate'])) {
                continue; // Пропускаем некорректные данные
            }

            // Удаляем тире из Direct, чтобы получить формат "BASEQUOTE"
            $currencyPair = strtoupper(str_replace(['/', ' - '], '', $item['Direct']));

            // Удаляем + или - из BaseRate
            $rate = str_replace(["+", "-"], "", (string) $item['BaseRate']);

            if (!empty($currencyPair) && is_numeric($rate)) {
                $result[$currencyPair] = number_format((float)$rate, 6, '.', '');
            }
        }

        return $result;
    }
}
