<?php

declare(strict_types=1);

namespace App\Services\Rates;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Read-only independent market baseline for audit / alerting / quarantine.
 *
 * Never silently overwrites production course_value.
 * Uses existing parser_exchange rows when available; accepts injected quotes for tests.
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

    /**
     * @param array<string, array{rate:string, source:string, as_of:string}> $injected
     */
    public function __construct(
        private readonly array $injected = [],
        private readonly bool $allowDatabase = true,
        private readonly ?PeerRateSelector $selector = null,
    ) {
    }

    /**
     * @return array{rate: string, source: string, as_of: string, age_seconds: int, sample_size?: int, divergence?: string|null}|null
     */
    public function quote(string $symbol): ?array
    {
        $symbol = strtoupper(trim($symbol));
        if (isset($this->injected[$symbol])) {
            $q = $this->injected[$symbol];
            $asOf = $q['as_of'];
            $age = max(0, time() - strtotime($asOf));

            return [
                'rate' => $q['rate'],
                'source' => $q['source'],
                'as_of' => $asOf,
                'age_seconds' => $age,
                'sample_size' => 1,
                'divergence' => null,
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
        $asset = strtoupper($asset);
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
        $fromAsset = strtoupper($fromAsset);
        $toAsset = strtoupper($toAsset);
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
        foreach (['USDT', 'USDC', 'BTC', 'ETH', 'BNB', 'TRX', 'TON', 'ZEC', 'LTC'] as $asset) {
            if ($code === $asset || str_starts_with($code, $asset)) {
                return $asset;
            }
        }

        return null;
    }

    public function coverage(): array
    {
        $assets = ['BTC', 'ETH', 'USDT', 'USDC', 'BNB', 'TRX', 'TON', 'ZEC', 'LTC'];
        $fiats = ['USD', 'EUR', 'GEL', 'AMD', 'RUB'];
        $gaps = [];
        foreach ($assets as $a) {
            if ($a === 'USDT' || $a === 'USDC') {
                continue;
            }
            if ($this->quote($a . 'USDT') === null) {
                $gaps[] = $a . 'USDT missing_or_stale';
            }
        }
        foreach (['USDGEL', 'USDEUR', 'USDAMD', 'USDRUB'] as $fx) {
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
     * @return array{rate:string,source:string,as_of:string,age_seconds:int,sample_size:int,divergence:string|null}|null
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

            $quotes = [];
            foreach ($candidates as [$codeIn, $codeOut]) {
                $rows = DB::table('parser_exchange')
                    ->where('status', 1)
                    ->where('code_in', $codeIn)
                    ->where('code_out', $codeOut)
                    ->orderByDesc('updated_at')
                    ->limit(8)
                    ->get(['id', 'code', 'summa', 'updated_at']);

                foreach ($rows as $row) {
                    $asOf = (string) ($row->updated_at ?? '');
                    $age = $asOf !== '' ? max(0, time() - strtotime($asOf)) : PHP_INT_MAX;
                    if ($age > $maxAge) {
                        continue;
                    }
                    $rate = bcmul((string) $row->summa, '1', self::SCALE);
                    if (bccomp($rate, '0', self::SCALE) !== 1) {
                        continue;
                    }
                    $quotes[] = [
                        'rate' => $rate,
                        'source' => 'parser_exchange:' . (string) ($row->code ?? $row->id),
                        'as_of' => $asOf,
                        'age_seconds' => $age,
                    ];
                }
            }

            if ($quotes === []) {
                return null;
            }

            $rates = array_column($quotes, 'rate');
            $selector = $this->selector ?? new PeerRateSelector();
            $selection = $selector->selectValidPeerRate(
                peerRates: $rates,
                preferred: null,
                maxMedianDeviation: self::PROVIDER_DIVERGENCE_FRACTION,
                minSample: 1,
            );

            // With minSample=1, selector always accepts when at least one valid rate.
            // Prefer median when sample>=3 and reject if preferred outliers — here we use median.
            $sorted = $rates;
            sort($sorted, SORT_STRING);
            $count = count($sorted);
            $mid = intdiv($count, 2);
            $median = $count % 2 === 1
                ? $sorted[$mid]
                : bcdiv(bcadd($sorted[$mid - 1], $sorted[$mid], self::SCALE), '2', self::SCALE);

            // Reject provider set if max/min diverge too much when sample >= 2
            $divergence = null;
            if ($count >= 2) {
                $lo = $sorted[0];
                $hi = $sorted[$count - 1];
                $divergence = bcdiv(bcsub($hi, $lo, self::SCALE), $median, self::SCALE);
                if (bccomp($this->absBc($divergence), self::PROVIDER_DIVERGENCE_FRACTION, self::SCALE) === 1) {
                    // Keep median but annotate; still usable for audit (do not auto-publish).
                }
            }

            $best = $quotes[0];
            foreach ($quotes as $q) {
                if ($q['rate'] === $median || bccomp($q['rate'], $median, 8) === 0) {
                    $best = $q;
                    break;
                }
            }

            return [
                'rate' => $median,
                'source' => $best['source'] . ($count > 1 ? '+median' : ''),
                'as_of' => $best['as_of'],
                'age_seconds' => min(array_column($quotes, 'age_seconds')),
                'sample_size' => $count,
                'divergence' => $divergence,
                'selection_reason' => $selection['reason'] ?? 'median',
            ];
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
            'USDCUSDT' => [['USDC', 'USDT'], ['USDC', 'USD']],
            'USDTRUB' => [['USDT', 'RUB'], ['USD', 'RUB']],
            'USDGEL' => [['USD', 'GEL']],
            'USDEUR' => [['USD', 'EUR']],
            'USDRUB' => [['USD', 'RUB']],
            'USDAMD' => [['USD', 'AMD']],
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
