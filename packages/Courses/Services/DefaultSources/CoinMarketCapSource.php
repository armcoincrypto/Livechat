<?php

namespace iEXPackages\Courses\Services\DefaultSources;

use App\Models\ParserApiKey;
use App\Models\ParserExchange;
use App\Services\Rates\CoinMarketCapFailureClassifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CoinMarketCapSource implements DefaultParserInterface
{

    public function getUrl(array $options = []): string
    {
        return 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest';
    }

    public function getParams(array $options = []): array
    {
        return [
            'start' => 1,
            'limit' => 950,
            'convert' => 'USD'
        ];
    }

    public function getHeaders(array $options = []): array
    {
        $keys = ParserApiKey::where([
            ['status', 1],
            ['provider_id', 'coinmarketcap']
        ])->inRandomOrder()->first();

        // Просто пропускаем update, если ключ не найден
        if ($keys) {
            $keys->update(['view_count' => $keys->view_count + 1]);
        }

        return [
            // Если ключ найден — возвращаем, если нет — используем apiKey из $options или пустую строку
            'X-CMC_PRO_API_KEY' => $options['apiKey'] ?? ($keys ? trim($keys->api_key) : ''),
            'Accept' => 'application/json'
        ];
    }

    public function parseResponse(string $response): array
    {
        $data = json_decode($response, true);

        if (is_array($data) && isset($data['status']) && is_array($data['status'])) {
            $errorCode = (int) ($data['status']['error_code'] ?? 0);
            if ($errorCode !== 0) {
                $classified = (new CoinMarketCapFailureClassifier())->classify($data, null, $response);
                Log::warning('coinmarketcap.provider_failure', [
                    'class' => $classified['class'],
                    'provider_code' => $classified['provider_code'],
                    // Never log API keys; message is sanitized by classifier.
                    'message' => $classified['message'],
                ]);

                return [
                    'error' => $classified['class'],
                    'provider_code' => $classified['provider_code'],
                    'provider_class' => $classified['class'],
                ];
            }
        }

        if (!isset($data['data']) || !is_array($data['data'])) {
            return ['error' => 'Invalid JSON format'];
        }

        // Получаем курсы валют к USD
        $defaultParser = cache()->remember('coinmarketcap-default-rates', Carbon::now()->addSeconds(10), function () {
            return ParserExchange::where('status', '=', 1)
                ->whereNotIn('id_group', [3])
                ->get()
                ->pluck('summa', 'name')
                ->toArray();
        });

        $result = [];

        $stableCurrencies = ['USD', 'USDT'];
        $fiatCurrencies = ['RUB', 'UAH', 'THB', 'AED', 'CNY'];

        // промежуточный массив курсов криптовалют по отношению к USD
        $usdRates = [];

        foreach ($data['data'] as $item) {
            if (!isset($item['symbol'], $item['quote']['USD']['price'])) {
                continue;
            }

            $symbol = strtoupper($item['symbol']);
            $usdPrice = $this->formatNumber((string) $item['quote']['USD']['price']);

            if ($usdPrice === '0' || $usdPrice === null || !is_numeric($usdPrice) || bccomp($usdPrice, '0', 18) === 0) {
                continue;
            }

            // Сохраняем USD курс криптовалюты
            $usdRates[$symbol] = $usdPrice;

            // Сразу сохраняем USD, USDT, USDC курсы (одинаковые значения)
            foreach ($stableCurrencies as $stableCurrency) {
                $result[$symbol . $stableCurrency] = $usdPrice;
            }

            // Сохраняем курсы криптовалют в фиатах
            foreach ($fiatCurrencies as $fiat) {
                $fiatKey = 'USD - ' . $fiat;
                if (isset($defaultParser[$fiatKey]) && is_numeric($defaultParser[$fiatKey])) {
                    $fiatMultiplier = $defaultParser[$fiatKey];
                    $fiatPrice = bcmul($usdPrice, $fiatMultiplier, 18);

                    $result[$symbol . $fiat] = $this->formatNumber($fiatPrice);
                }
            }
        }

        // Дополнительно рассчитываем все крипто-пары между собой (BTCETH, ETHLTC и т.п.)
        $symbols = array_keys($usdRates);

        foreach ($symbols as $baseSymbol) {
            foreach ($symbols as $quoteSymbol) {
                if ($baseSymbol === $quoteSymbol) {
                    continue;
                }

                $pairKey = $baseSymbol . $quoteSymbol;

                if (bccomp($usdRates[$quoteSymbol], '0', 18) !== 0) {
                    $pairRate = bcdiv($usdRates[$baseSymbol], $usdRates[$quoteSymbol], 18);
                    $result[$pairKey] = $this->formatNumber($pairRate);
                }
            }
        }

        return $result;
    }


    private function formatNumber(string $number): string
    {
        // Убираем пробелы и ненужные символы
        $number = trim($number);

        // Проверяем, является ли число корректным
        if ($number === '' || strtolower($number) === 'null' || !is_numeric($number)) {
            return '0';
        }

        // Если число в экспоненциальной нотации, преобразуем его в строку
        if (stripos($number, 'E') !== false) {
            $number = sprintf('%.18f', $number); // Преобразуем E-notation в обычный формат
        }

        return bcdiv($number, '1', 18); // Используем bcdiv для высокой точности
    }
}
