<?php

namespace iEXPackages\Courses\Services\DefaultSources;

class MoexSource implements DefaultParserInterface
{
    public function getUrl(array $options = []): string
    {
        return "https://iss.moex.com/iss/statistics/engines/currency/markets/selt/rates.json";
    }

    public function getParams(array $options = []): array
    {
        return [];
    }

    public function parseResponse(string $response): array
    {
        $data = json_decode($response, true);

        if (!isset($data['cbrf']['columns'], $data['cbrf']['data'][0]) || !isset($data['wap_rates']['columns'], $data['wap_rates']['data'][0])) {
            return ['error' => 'Invalid JSON format'];
        }

        $result = [];

        // Обрабатываем CBRF данные
        $cbrfColumns = $data['cbrf']['columns'];
        $cbrfData = $data['cbrf']['data'][0];
        $cbrfMapped = array_combine($cbrfColumns, $cbrfData);

        if (isset($cbrfMapped['CBRF_USD_LAST'])) {
            $result['RUBUSD'] = number_format((float)$cbrfMapped['CBRF_USD_LAST'], 6, '.', '');
        }
        if (isset($cbrfMapped['CBRF_EUR_LAST'])) {
            $result['RUBEUR'] = number_format((float)$cbrfMapped['CBRF_EUR_LAST'], 6, '.', '');
        }

        // Обрабатываем WAP Rates
        $wapColumns = $data['wap_rates']['columns'];
        foreach ($data['wap_rates']['data'] as $wapRow) {
            $wapMapped = array_combine($wapColumns, $wapRow);

            if (isset($wapMapped['secid'], $wapMapped['price'])) {
                $currencyPair = strtoupper(str_replace(['_', 'TOM'], '', $wapMapped['secid']));
                $result[$currencyPair] = number_format((float)$wapMapped['price'], 6, '.', '');
            }
        }

        return $result;
    }
}
