<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Services\Rates\ProviderIntegrity\CertifiedSymbolFallback;
use App\Services\Rates\ProviderIntegrity\DerivedLegLastKnownGoodStore;
use App\Services\Rates\ProviderIntegrity\ExchangeSymbolStatusRegistry;
use App\Services\Rates\ProviderIntegrity\ProviderLegAggregator;
use App\Services\Rates\ProviderIntegrity\ProviderQuoteClassifier;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Read-only independent market baseline for audit / alerting / quarantine.
 *
 * Never silently overwrites production course_value.
 * Uses existing parser_exchange rows when available; accepts injected quotes for tests.
 *
 * Provider integrity (P0): BREAK/delisted/stale/no-book quotes never enter the median.
 * Distinct-provider aggregation + certified fallbacks + LKG TTL.
 *
 * Freshness policy (production target):
 * - crypto: <= 15 minutes
 * - fiat:   <= 6 hours
 */
final class IndependentMarketBaseline
{
    public const CRYPTO_MAX_AGE_SECONDS = 900; // 15m

    public const FIAT_MAX_AGE_SECONDS = 21600; // 6h

    public const PROVIDER_DIVERGENCE_FRACTION = '0.02'; // 2%

    public const SCALE = 18;

    private readonly ProviderLegAggregator $aggregator;

    private readonly CertifiedSymbolFallback $fallback;

    private readonly DerivedLegLastKnownGoodStore $lkg;

    /**
     * @param array<string, array{rate:string, source:string, as_of:string}> $injected
     */
    public function __construct(
        private readonly array $injected = [],
        private readonly bool $allowDatabase = true,
        private readonly ?PeerRateSelector $selector = null,
        private readonly bool $allowCertifiedFallback = true,
        private readonly bool $allowLastKnownGood = true,
        ?ProviderLegAggregator $aggregator = null,
        ?CertifiedSymbolFallback $fallback = null,
        ?DerivedLegLastKnownGoodStore $lkg = null,
        ?ExchangeSymbolStatusRegistry $exchangeStatus = null,
    ) {
        $registry = $exchangeStatus ?? new ExchangeSymbolStatusRegistry();
        $this->aggregator = $aggregator ?? new ProviderLegAggregator(
            classifier: new ProviderQuoteClassifier($registry),
        );
        $this->fallback = $fallback ?? new CertifiedSymbolFallback(
            quoteFn: function (string $sym): ?array {
                // Nested quotes for fallback crosses must not recurse into fallbacks.
                $inner = new self(
                    injected: $this->injected,
                    allowDatabase: $this->allowDatabase,
                    selector: $this->selector,
                    allowCertifiedFallback: false,
                    allowLastKnownGood: false,
                );

                return $inner->quote($sym);
            },
        );
        $this->lkg = $lkg ?? new DerivedLegLastKnownGoodStore();
    }

    /**
     * @return array{
     *   rate: string,
     *   source: string,
     *   as_of: string,
     *   age_seconds: int,
     *   sample_size?: int,
     *   divergence?: string|null,
     *   quality_state?: string,
     *   provider_count?: int,
     *   distinct_providers?: list<string>,
     *   excluded?: list<array<string,mixed>>,
     *   selection_reason?: string
     * }|null
     */
    public function quote(string $symbol): ?array
    {
        $symbol = strtoupper(trim($symbol));
        if (isset($this->injected[$symbol])) {
            $q = $this->injected[$symbol];
            $rate = (string) ($q['rate'] ?? '');
            if ($rate === '' || !is_numeric($rate) || bccomp($rate, '0', self::SCALE) !== 1) {
                return null;
            }
            $asOf = $q['as_of'];
            $age = max(0, time() - strtotime($asOf));

            return [
                'rate' => bcmul($rate, '1', self::SCALE),
                'source' => $q['source'],
                'as_of' => $asOf,
                'age_seconds' => $age,
                'sample_size' => 1,
                'divergence' => null,
                'quality_state' => (string) ($q['quality_state'] ?? ProviderLegAggregator::QUALITY_HEALTHY),
                'provider_count' => 1,
                'distinct_providers' => [(string) ($q['source'] ?? 'injected')],
                'excluded' => [],
                'selection_reason' => 'injected',
            ];
        }

        if (!$this->allowDatabase) {
            return null;
        }

        return $this->fromParserExchange($symbol);
    }

    /**
     * @return array{rate:string,source:string,as_of:string,age_seconds:int,components:array}|null
     */
    public function cryptoGel(string $asset): ?array
    {
        $asset = strtoupper($asset);
        $crypto = $this->quote($asset . 'USDT');
        $gel = $this->quote('USDGEL');
        if ($crypto === null || $gel === null) {
            return null;
        }
        if ($crypto['age_seconds'] > self::CRYPTO_MAX_AGE_SECONDS) {
            return null;
        }
        if ($gel['age_seconds'] > self::FIAT_MAX_AGE_SECONDS) {
            return null;
        }

        $rate = bcmul($crypto['rate'], $gel['rate'], self::SCALE);

        return [
            'rate' => $rate,
            'source' => $crypto['source'] . '*' . $gel['source'],
            'as_of' => max($crypto['as_of'], $gel['as_of']),
            'age_seconds' => max($crypto['age_seconds'], $gel['age_seconds']),
            'components' => [
                'crypto_usdt' => $crypto,
                'usd_gel' => $gel,
            ],
        ];
    }

    /**
     * @return array{assets:list<string>,fiats:list<string>,sources:list<string>,freshness:array,gaps:list<string>}
     */

    /**
     * Independent crypto→RUB baseline: crypto/USDT (or USD) × USD/RUB.
     *
     * @return array{rate:string,source:string,as_of:string,age_seconds:int,components:array}|null
     */
    public function cryptoRub(string $asset): ?array
    {
        $asset = self::assetFromCode($asset) ?? strtoupper(trim($asset));
        $crypto = $this->quote($asset . 'USDT');
        if ($crypto === null) {
            $crypto = $this->quote($asset . 'USD');
        }
        $rub = $this->quote('USDRUB');
        if ($crypto === null || $rub === null) {
            return null;
        }
        if ($crypto['age_seconds'] > self::CRYPTO_MAX_AGE_SECONDS) {
            return null;
        }
        if ($rub['age_seconds'] > self::FIAT_MAX_AGE_SECONDS) {
            return null;
        }

        $rate = bcmul($crypto['rate'], $rub['rate'], self::SCALE);

        return [
            'rate' => $rate,
            'source' => $crypto['source'] . '*' . $rub['source'],
            'as_of' => max($crypto['as_of'], $rub['as_of']),
            'age_seconds' => max($crypto['age_seconds'], $rub['age_seconds']),
            'components' => [
                'crypto_usdt' => $crypto,
                'usd_rub' => $rub,
            ],
        ];
    }

    /**
     * Bridged crypto→crypto baseline via USDT (never BestChange).
     * Orientation: destination units received per 1 source unit.
     *
     * @return array{rate:string,source:string,as_of:string,age_seconds:int,components:array,path:string}|null
     */
    public function cryptoViaUsdt(string $fromAsset, string $toAsset): ?array
    {
        $fromAsset = self::assetFromCode($fromAsset) ?? strtoupper(trim($fromAsset));
        $toAsset = self::assetFromCode($toAsset) ?? strtoupper(trim($toAsset));
        if ($fromAsset === '' || $toAsset === '' || $fromAsset === $toAsset) {
            return null;
        }

        if ($fromAsset === 'USDT' || $fromAsset === 'USDC') {
            $to = $this->quote($toAsset . 'USDT') ?? $this->quote($toAsset . 'USD');
            if ($to === null || $to['age_seconds'] > self::CRYPTO_MAX_AGE_SECONDS) {
                return null;
            }
            if (bccomp($to['rate'], '0', self::SCALE) !== 1) {
                return null;
            }
            $rate = bcdiv('1', $to['rate'], self::SCALE);

            return [
                'rate' => $rate,
                'source' => '1/' . $to['source'],
                'as_of' => $to['as_of'],
                'age_seconds' => $to['age_seconds'],
                'path' => 'stable_to_crypto_via_usdt',
                'components' => ['to_usdt' => $to],
            ];
        }

        if ($toAsset === 'USDT' || $toAsset === 'USDC') {
            $from = $this->quote($fromAsset . 'USDT') ?? $this->quote($fromAsset . 'USD');
            if ($from === null || $from['age_seconds'] > self::CRYPTO_MAX_AGE_SECONDS) {
                return null;
            }

            return [
                'rate' => $from['rate'],
                'source' => $from['source'],
                'as_of' => $from['as_of'],
                'age_seconds' => $from['age_seconds'],
                'path' => 'crypto_to_stable_via_usdt',
                'components' => ['from_usdt' => $from],
            ];
        }

        $from = $this->quote($fromAsset . 'USDT') ?? $this->quote($fromAsset . 'USD');
        $to = $this->quote($toAsset . 'USDT') ?? $this->quote($toAsset . 'USD');
        if ($from === null || $to === null) {
            return null;
        }
        if ($from['age_seconds'] > self::CRYPTO_MAX_AGE_SECONDS || $to['age_seconds'] > self::CRYPTO_MAX_AGE_SECONDS) {
            return null;
        }
        if (bccomp($to['rate'], '0', self::SCALE) !== 1) {
            return null;
        }

        $rate = bcdiv($from['rate'], $to['rate'], self::SCALE);

        return [
            'rate' => $rate,
            'source' => $from['source'] . '/' . $to['source'],
            'as_of' => max($from['as_of'], $to['as_of']),
            'age_seconds' => max($from['age_seconds'], $to['age_seconds']),
            'path' => 'crypto_to_crypto_via_usdt',
            'components' => [
                'from_usdt' => $from,
                'to_usdt' => $to,
            ],
        ];
    }

    /**
     * Map local designation_xml (e.g. USDTTRC20, BNBBEP20) to baseline asset code.
     */
    public static function assetFromCode(string $code): ?string
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }
        // GRAM is the local BestChange-facing TON ticker.
        if ($code === 'GRAM' || str_starts_with($code, 'GRAM')) {
            return 'TON';
        }

        // USD payment rails (Zelle/Revolut/Cash/etc.) are USD-pegged for RUB baselines.
        // Must run before USDT/USDC prefix matching would miss them entirely.
        if (
            str_ends_with($code, 'USD')
            && !str_contains($code, 'USDT')
            && !str_contains($code, 'USDC')
        ) {
            return 'USDT';
        }

        // PEPE local designation is title-case "Pepe"; normalize already uppercased above.
        if ($code === 'PEPE') {
            return 'PEPE';
        }

        foreach ([
            'USDT', 'USDC', 'TUSD', 'BTC', 'ETH', 'BNB', 'TRX', 'TON', 'ZEC', 'LTC',
            'SOL', 'XMR', 'XRP', 'DOGE', 'ADA', 'BCH', 'ETC', 'DASH',
            'DOT', 'NEO', 'XLM', 'XTZ', 'SHIB', 'PEPE',
        ] as $asset) {
            if ($code === $asset || str_starts_with($code, $asset)) {
                return $asset;
            }
        }

        return null;
    }

    public function coverage(): array
    {
        $assets = [
            'BTC', 'ETH', 'USDT', 'USDC', 'TUSD', 'BNB', 'TRX', 'TON', 'ZEC', 'LTC',
            'SOL', 'XMR', 'XRP', 'DOGE', 'ADA', 'BCH', 'ETC', 'DASH',
            'DOT', 'NEO', 'XLM', 'XTZ', 'SHIB', 'PEPE',
        ];
        $fiats = [
            'USD', 'EUR', 'GEL', 'AMD', 'RUB', 'UAH', 'KZT', 'BYN', 'AED', 'CNY',
            'IDR', 'INR', 'CAD', 'THB', 'KGS', 'TJS', 'UZS',
        ];
        $gaps = [];
        foreach ($assets as $a) {
            if ($a === 'USDT' || $a === 'USDC' || $a === 'TUSD') {
                continue;
            }
            if ($this->quote($a . 'USDT') === null) {
                $gaps[] = $a . 'USDT missing_or_stale';
            }
        }
        foreach ([
            'USDGEL', 'USDEUR', 'USDAMD', 'USDRUB', 'USDUAH', 'USDKZT', 'USDBYN', 'USDAED',
            'USDCNY', 'USDIDR', 'USDINR', 'USDCAD', 'USDTHB', 'USDKGS', 'USDTJS', 'USDUZS',
        ] as $fx) {
            if ($this->quote($fx) === null) {
                $gaps[] = $fx . ' missing_or_stale';
            }
        }

        return [
            'assets' => $assets,
            'fiats' => $fiats,
            'sources' => ['parser_exchange_multi_provider', 'injected_fixtures'],
            'freshness' => [
                'crypto_max_age_seconds' => self::CRYPTO_MAX_AGE_SECONDS,
                'fiat_max_age_seconds' => self::FIAT_MAX_AGE_SECONDS,
                'provider_divergence_fraction' => self::PROVIDER_DIVERGENCE_FRACTION,
            ],
            'gaps' => $gaps,
        ];
    }

    /**
     * @return array{
     *   rate:string,
     *   source:string,
     *   as_of:string,
     *   age_seconds:int,
     *   sample_size:int,
     *   divergence:string|null,
     *   quality_state:string,
     *   provider_count:int,
     *   distinct_providers:list<string>,
     *   excluded:list<array<string,mixed>>,
     *   selection_reason:string
     * }|null
     */
    private function fromParserExchange(string $symbol): ?array
    {
        try {
            if (!class_exists(DB::class)) {
                return null;
            }

            $candidates = $this->symbolCandidates($symbol);
            if ($candidates === []) {
                return null;
            }

            $isFiat = str_starts_with($symbol, 'USD') && !str_ends_with($symbol, 'USDT');
            $maxAge = $isFiat ? self::FIAT_MAX_AGE_SECONDS : self::CRYPTO_MAX_AGE_SECONDS;

            $rows = [];
            foreach ($candidates as [$codeIn, $codeOut]) {
                // Load enabled + disabled so integrity can classify BREAK rows that are still status=1
                // and report excluded providers. Aggregation only keeps eligible.
                $dbRows = DB::table('parser_exchange')
                    ->where('code_in', $codeIn)
                    ->where('code_out', $codeOut)
                    ->orderByDesc('updated_at')
                    ->limit(24)
                    ->get(['id', 'code', 'summa', 'updated_at', 'status', 'code_in', 'code_out']);

                foreach ($dbRows as $row) {
                    $asOf = (string) ($row->updated_at ?? '');
                    if ($asOf === '') {
                        continue;
                    }
                    $phpAge = max(0, time() - strtotime($asOf));
                    $age = $phpAge;
                    if ($phpAge > $maxAge) {
                        try {
                            $mysqlAgeRow = DB::selectOne(
                                'SELECT TIMESTAMPDIFF(SECOND, ?, NOW()) AS age_seconds',
                                [$asOf]
                            );
                            if ($mysqlAgeRow !== null) {
                                $mysqlAge = max(0, (int) $mysqlAgeRow->age_seconds);
                                if ($mysqlAge <= $maxAge) {
                                    $age = $mysqlAge;
                                }
                            }
                        } catch (Throwable) {
                            // keep php age
                        }
                    }
                    $rows[] = [
                        'code' => (string) ($row->code ?? ''),
                        'summa' => (string) ($row->summa ?? '0'),
                        'status' => (int) ($row->status ?? 0),
                        'age_seconds' => $age,
                        'as_of' => $asOf,
                        'code_in' => (string) ($row->code_in ?? $codeIn),
                        'code_out' => (string) ($row->code_out ?? $codeOut),
                    ];
                }
            }

            $agg = $this->aggregator->aggregate($rows, $maxAge);
            if ($agg['ok'] && is_string($agg['rate'])) {
                $out = [
                    'rate' => $agg['rate'],
                    'source' => (string) $agg['source'],
                    'as_of' => (string) $agg['as_of'],
                    'age_seconds' => (int) $agg['age_seconds'],
                    'sample_size' => (int) $agg['provider_count'],
                    'divergence' => $agg['divergence'],
                    'quality_state' => (string) $agg['quality_state'],
                    'provider_count' => (int) $agg['provider_count'],
                    'distinct_providers' => $agg['distinct_providers'],
                    'excluded' => $agg['excluded'],
                    'selection_reason' => (string) ($agg['reason'] ?? 'median'),
                ];
                if (in_array($out['quality_state'], [
                    ProviderLegAggregator::QUALITY_HEALTHY,
                    ProviderLegAggregator::QUALITY_DEGRADED_SINGLE,
                ], true)) {
                    $this->lkg->put($symbol, $out);
                }

                return $out;
            }

            // Certified peer fallback (e.g. TONUSDT via BC GRAM book when CEX is BREAK).
            if ($this->allowCertifiedFallback) {
                $fb = $this->fallback->resolve($symbol);
                if (is_array($fb) && !empty($fb['ok']) && is_string($fb['rate'] ?? null)) {
                    $out = [
                        'rate' => $fb['rate'],
                        'source' => (string) $fb['source'],
                        'as_of' => (string) $fb['as_of'],
                        'age_seconds' => (int) $fb['age_seconds'],
                        'sample_size' => (int) $fb['provider_count'],
                        'divergence' => null,
                        'quality_state' => (string) $fb['quality_state'],
                        'provider_count' => (int) $fb['provider_count'],
                        'distinct_providers' => $fb['distinct_providers'],
                        'excluded' => $agg['excluded'],
                        'selection_reason' => (string) ($fb['reason'] ?? 'certified_fallback'),
                    ];
                    if ($out['quality_state'] === ProviderLegAggregator::QUALITY_HEALTHY
                        || $out['quality_state'] === ProviderLegAggregator::QUALITY_DEGRADED_FALLBACK) {
                        $this->lkg->put($symbol, $out);
                    }

                    return $out;
                }
            }

            // Bounded LKG — never report HEALTHY.
            if ($this->allowLastKnownGood) {
                $ttl = $isFiat
                    ? (int) (function_exists('config')
                        ? config('rates_provider_integrity.lkg_ttl_fiat_seconds', self::FIAT_MAX_AGE_SECONDS)
                        : self::FIAT_MAX_AGE_SECONDS)
                    : (int) (function_exists('config')
                        ? config('rates_provider_integrity.lkg_ttl_crypto_seconds', 3600)
                        : 3600);
                $lkg = $this->lkg->getFresh($symbol, $ttl);
                if ($lkg !== null) {
                    return [
                        'rate' => $lkg['rate'],
                        'source' => $lkg['source'],
                        'as_of' => $lkg['as_of'],
                        'age_seconds' => $lkg['age_seconds'],
                        'sample_size' => 0,
                        'divergence' => null,
                        'quality_state' => ProviderLegAggregator::QUALITY_DEGRADED_SINGLE,
                        'provider_count' => 0,
                        'distinct_providers' => $lkg['source_set'],
                        'excluded' => $agg['excluded'],
                        'selection_reason' => 'last_known_good',
                    ];
                }
            }

            // Quarantine — do not invent a rate; do not use BREAK medians.
            return null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{0:string,1:string}>
     */
    private function symbolCandidates(string $symbol): array
    {
        return match ($symbol) {
            'BTCUSDT' => [['BTC', 'USDT'], ['BTC', 'USD']],
            'ETHUSDT' => [['ETH', 'USDT'], ['ETH', 'USD']],
            'BNBUSDT' => [['BNB', 'USDT'], ['BNB', 'USD']],
            'TRXUSDT' => [['TRX', 'USDT'], ['TRX', 'USD']],
            'TONUSDT' => [['TON', 'USDT'], ['TON', 'USD']],
            'ZECUSDT' => [['ZEC', 'USDT'], ['ZEC', 'USD']],
            'ZECUSD' => [['ZEC', 'USD']],
            'LTCUSDT' => [['LTC', 'USDT'], ['LTC', 'USD']],
            'SOLUSDT' => [['SOL', 'USDT'], ['SOL', 'USD']],
            'XMRUSDT' => [['XMR', 'USDT'], ['XMR', 'USD']],
            'XRPUSDT' => [['XRP', 'USDT'], ['XRP', 'USD']],
            'DOGEUSDT' => [['DOGE', 'USDT'], ['DOGE', 'USD']],
            'ADAUSDT' => [['ADA', 'USDT'], ['ADA', 'USD']],
            'BCHUSDT' => [['BCH', 'USDT'], ['BCH', 'USD']],
            'ETCUSDT' => [['ETC', 'USDT'], ['ETC', 'USD']],
            'DASHUSDT' => [['DASH', 'USDT'], ['DASH', 'USD']],
            'DOTUSDT' => [['DOT', 'USDT'], ['DOT', 'USD']],
            'NEOUSDT' => [['NEO', 'USDT'], ['NEO', 'USD']],
            'XLMUSDT' => [['XLM', 'USDT'], ['XLM', 'USD']],
            'XTZUSDT' => [['XTZ', 'USDT'], ['XTZ', 'USD']],
            'SHIBUSDT' => [['SHIB', 'USDT'], ['SHIB', 'USD']],
            'PEPEUSDT' => [['PEPE', 'USDT'], ['PEPE', 'USD'], ['1000PEPE', 'USDT']],
            'TUSDUSDT' => [['TUSD', 'USDT'], ['TUSD', 'USD']],
            'USDCUSDT' => [['USDC', 'USDT'], ['USDC', 'USD']],
            'USDTRUB' => [['USDT', 'RUB'], ['USD', 'RUB']],
            'USDGEL' => [['USD', 'GEL']],
            'USDEUR' => [['USD', 'EUR']],
            'USDRUB' => [['USD', 'RUB']],
            'USDAMD' => [['USD', 'AMD']],
            'USDUAH' => [['USD', 'UAH']],
            'USDKZT' => [['USD', 'KZT']],
            'USDBYN' => [['USD', 'BYN']],
            'USDAED' => [['USD', 'AED']],
            'USDCNY' => [['USD', 'CNY']],
            'USDIDR' => [['USD', 'IDR']],
            'USDINR' => [['USD', 'INR']],
            'USDCAD' => [['USD', 'CAD']],
            'USDTHB' => [['USD', 'THB']],
            'USDKGS' => [['USD', 'KGS']],
            'USDTJS' => [['USD', 'TJS']],
            'USDUZS' => [['USD', 'UZS']],
            default => [],
        };
    }

    private function absBc(string $value): string
    {
        return bccomp($value, '0', self::SCALE) === -1
            ? bcmul($value, '-1', self::SCALE)
            : $value;
    }
}
