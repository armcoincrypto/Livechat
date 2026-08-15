<?php

namespace iEXPackages\Courses\Services\DefaultSources;

use Illuminate\Support\Facades\Log;

class MEXCSource implements DefaultParserInterface
{
    public function getUrl(array $options = []): string
    {
        return 'https://api.mexc.com/api/v3/ticker/bookTicker';
    }

    public function getParams(array $options = []): array
    {
        return [];
    }

    public function parseResponse(string $response): array
    {
        $data = json_decode($response, true);

        if (!is_array($data)) {
            Log::warning('MEXCSource: invalid JSON', [
                'json_error' => json_last_error_msg(),
            ]);

            return ['error' => 'Invalid JSON format'];
        }

        // v3 bookTicker: top-level array of objects
        // legacy: { data: [...] }
        $items = [];

        if (array_is_list($data)) {
            $items = $data;
        } elseif (isset($data['data']) && is_array($data['data'])) {
            $items = $data['data'];
        } else {
            return ['error' => 'Unexpected JSON structure'];
        }

        $result = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $rawSymbol = (string)($item['symbol'] ?? '');
            $symbol = $this->normalizeSymbol($rawSymbol);
            if ($symbol === '') {
                continue;
            }

            // v3 keys
            $bidRaw = $item['bidPrice'] ?? $item['bid'] ?? null;
            $askRaw = $item['askPrice'] ?? $item['ask'] ?? null;
            $lastRaw = $item['last'] ?? $item['lastPrice'] ?? $item['price'] ?? null;

            $bid = $this->formatNumber($bidRaw);
            $ask = $this->formatNumber($askRaw);

            // В bookTicker нет last, поэтому default считаем аккуратно:
            // - если есть last — используем
            // - иначе midpoint (bid+ask)/2
            // - иначе bid/ask (что есть)
            $default = $this->formatNumber($lastRaw);
            if ($default === '0') {
                if ($bid !== '0' && $ask !== '0') {
                    $default = rtrim(rtrim(bcdiv(bcadd($bid, $ask, 18), '2', 18), '0'), '.');
                } elseif ($bid !== '0') {
                    $default = $bid;
                } elseif ($ask !== '0') {
                    $default = $ask;
                }
            }

            if ($bid === '0' && $ask === '0' && $default === '0') {
                continue;
            }

            $result[$symbol] = [
                'bid' => $bid,
                'ask' => $ask,
                'default' => $default,
            ];
        }

        return $result;
    }

    /**
     * Нормализует символ пары.
     *
     * Правила:
     * - убираем пробелы и подчёркивания;
     * - приводим к верхнему регистру;
     * - допускаем только ASCII A-Z/0-9 (иначе пропускаем), чтобы не ломать downstream.
     */
    private function normalizeSymbol(string $symbol): string
    {
        $symbol = trim($symbol);
        if ($symbol === '') {
            return '';
        }

        // Убираем пробелы/табуляции и подчёркивания
        $symbol = preg_replace('/[\s_]+/u', '', $symbol) ?? '';
        if ($symbol === '') {
            return '';
        }

        // В большинстве случаев торговые пары в ASCII
        $symbol = strtoupper($symbol);

        // Отсекаем нестандартные символы (в т.ч. китайские и т.п.)
        if (!preg_match('/^[A-Z0-9]+$/', $symbol)) {
            return '';
        }

        return $symbol;
    }

    private function formatNumber(mixed $number): string
    {
        if ($number === null) {
            return '0';
        }

        $number = trim((string)$number);
        if ($number === '' || !is_numeric($number)) {
            return '0';
        }

        // Экспоненциальная нотация (например 1.2E-8)
        if (stripos($number, 'e') !== false) {
            $number = number_format((float)$number, 18, '.', '');
        }

        // Приводим к строке с 18 знаками после запятой и убираем хвостовые нули
        return rtrim(rtrim(bcdiv($number, '1', 18), '0'), '.');
    }
}
