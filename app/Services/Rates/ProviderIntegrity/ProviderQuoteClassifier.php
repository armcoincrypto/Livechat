<?php

declare(strict_types=1);

namespace App\Services\Rates\ProviderIntegrity;

/**
 * Parse parser_exchange.code into provider + market symbol and classify eligibility.
 */
final class ProviderQuoteClassifier
{
    public const HEALTHY = 'HEALTHY';

    public const BREAK = 'BREAK';

    public const DELISTED = 'DELISTED';

    public const NO_BOOK = 'NO_BOOK';

    public const NO_KLINES = 'NO_KLINES';

    public const STALE = 'STALE';

    public const OUTLIER = 'OUTLIER';

    public const DISABLED = 'DISABLED';

    public const NON_POSITIVE = 'NON_POSITIVE';

    public const UNKNOWN = 'UNKNOWN';

    public function __construct(
        private readonly ExchangeSymbolStatusRegistry $registry = new ExchangeSymbolStatusRegistry(),
    ) {
    }

    /**
     * @return array{provider:string,symbol:string,code:string}
     */
    public function parseCode(string $code, string $codeIn = '', string $codeOut = ''): array
    {
        $raw = trim($code);
        $inner = trim($raw, "[] \t");
        $provider = 'unknown';
        $pair = '';
        if ($inner !== '') {
            $parts = explode('_', $inner, 2);
            $provider = strtolower($parts[0] ?? 'unknown');
            $pair = strtoupper(str_replace('-', '', (string) ($parts[1] ?? '')));
            // strip ask/bid suffixes: ton-usdt_ask → already split wrong; handle:
            // rapira_ton-usdt_ask → parts[1]=ton-usdt_ask
            if (str_contains((string) ($parts[1] ?? ''), '-')) {
                $tail = (string) $parts[1];
                $tail = preg_replace('/_(ask|bid)$/i', '', $tail) ?? $tail;
                $pair = strtoupper(str_replace('-', '', $tail));
            }
        }
        if ($pair === '' && $codeIn !== '' && $codeOut !== '') {
            $pair = strtoupper($codeIn . $codeOut);
        }

        return [
            'provider' => $provider,
            'symbol' => $pair,
            'code' => $raw,
        ];
    }

    /**
     * @param array{
     *   code?:string,
     *   code_in?:string,
     *   code_out?:string,
     *   summa?:string|float|int,
     *   status?:int,
     *   age_seconds?:int,
     *   book_available?:bool|null,
     *   kline_available?:bool|null
     * } $row
     * @return array{
     *   class:string,
     *   eligible:bool,
     *   provider:string,
     *   symbol:string,
     *   exchange_status:string,
     *   reason:string
     * }
     */
    public function classify(array $row, int $maxAgeSeconds): array
    {
        $parsed = $this->parseCode(
            (string) ($row['code'] ?? ''),
            (string) ($row['code_in'] ?? ''),
            (string) ($row['code_out'] ?? ''),
        );
        $provider = $parsed['provider'];
        $symbol = $parsed['symbol'];
        $ex = $this->registry->status($provider, $symbol);

        if ((int) ($row['status'] ?? 0) !== 1) {
            return $this->result(self::DISABLED, false, $provider, $symbol, $ex, 'parser_disabled');
        }

        $rate = (string) ($row['summa'] ?? '0');
        if ($rate === '' || !is_numeric($rate) || bccomp($rate, '0', 18) !== 1) {
            return $this->result(self::NON_POSITIVE, false, $provider, $symbol, $ex, 'non_positive');
        }

        if ($this->registry->isExplicitlyUntradable($provider, $symbol)) {
            $cls = $ex === ExchangeSymbolStatusRegistry::BREAK ? self::BREAK : self::DELISTED;

            return $this->result($cls, false, $provider, $symbol, $ex, 'exchange_untradable:' . $ex);
        }

        if (array_key_exists('book_available', $row) && $row['book_available'] === false
            && array_key_exists('kline_available', $row) && $row['kline_available'] === false) {
            return $this->result(self::NO_BOOK, false, $provider, $symbol, $ex, 'no_book_no_kline');
        }

        $age = (int) ($row['age_seconds'] ?? 0);
        if ($age > $maxAgeSeconds) {
            return $this->result(self::STALE, false, $provider, $symbol, $ex, 'stale_age');
        }

        return $this->result(self::HEALTHY, true, $provider, $symbol, $ex, 'eligible');
    }

    /**
     * @return array{class:string,eligible:bool,provider:string,symbol:string,exchange_status:string,reason:string}
     */
    private function result(
        string $class,
        bool $eligible,
        string $provider,
        string $symbol,
        string $ex,
        string $reason,
    ): array {
        return [
            'class' => $class,
            'eligible' => $eligible,
            'provider' => $provider,
            'symbol' => $symbol,
            'exchange_status' => $ex,
            'reason' => $reason,
        ];
    }
}
