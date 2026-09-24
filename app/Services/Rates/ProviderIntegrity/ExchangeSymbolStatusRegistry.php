<?php

declare(strict_types=1);

namespace App\Services\Rates\ProviderIntegrity;

/**
 * Exchange symbol trading status for provider eligibility.
 *
 * Prefer constructor overrides / config denylist in tests and fail-closed
 * production defaults. Optional Binance probe is disabled by default to keep
 * quote paths deterministic and offline-safe.
 */
final class ExchangeSymbolStatusRegistry
{
    public const TRADING = 'TRADING';

    public const BREAK = 'BREAK';

    public const DELISTED = 'DELISTED';

    public const HALT = 'HALT';

    public const UNKNOWN = 'UNKNOWN';

    /** @var array<string, array<string, string>> provider => symbol => status */
    private array $status;

    /**
     * @param array<string, array<string, string>> $overrides
     */
    public function __construct(
        array $overrides = [],
        private readonly bool $probeBinance = false,
    ) {
        $cfg = [];
        if (function_exists('config')) {
            $cfg = (array) config('rates_provider_integrity.exchange_status', []);
        } else {
            $path = dirname(__DIR__, 3) . '/config/rates_provider_integrity.php';
            if (is_file($path)) {
                $all = include $path;
                $cfg = is_array($all) ? (array) ($all['exchange_status'] ?? []) : [];
            }
        }
        $this->status = array_replace_recursive($cfg, $overrides);
    }

    public function status(string $provider, string $symbol): string
    {
        $provider = strtolower(trim($provider));
        $symbol = strtoupper(trim($symbol));
        if ($provider === '' || $symbol === '') {
            return self::UNKNOWN;
        }

        $known = $this->status[$provider][$symbol] ?? null;
        if (is_string($known) && $known !== '') {
            return strtoupper($known);
        }

        if ($this->probeBinance && $provider === 'binance') {
            $probed = $this->probeBinanceSymbol($symbol);
            if ($probed !== self::UNKNOWN) {
                $this->status[$provider][$symbol] = $probed;

                return $probed;
            }
        }

        return self::UNKNOWN;
    }

    public function isTradable(string $provider, string $symbol): bool
    {
        $st = $this->status($provider, $symbol);

        return $st === self::TRADING || $st === self::UNKNOWN;
    }

    public function isExplicitlyUntradable(string $provider, string $symbol): bool
    {
        $st = $this->status($provider, $symbol);

        return in_array($st, [self::BREAK, self::DELISTED, self::HALT], true);
    }

    private function probeBinanceSymbol(string $symbol): string
    {
        $url = 'https://api.binance.com/api/v3/exchangeInfo?symbol=' . rawurlencode($symbol);
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 2,
                'header' => "User-Agent: exswaping-provider-integrity\r\n",
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            return self::UNKNOWN;
        }
        $data = json_decode($raw, true);
        $st = strtoupper((string) ($data['symbols'][0]['status'] ?? ''));
        if ($st === '') {
            return self::UNKNOWN;
        }
        if ($st === self::TRADING) {
            return self::TRADING;
        }
        if (in_array($st, [self::BREAK, self::HALT], true)) {
            return $st;
        }

        return self::DELISTED;
    }
}
