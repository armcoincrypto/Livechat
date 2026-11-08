<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Models\Currency;
use App\Models\DirectionExchange;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Canonical ZELLEUSD→destination benchmark hierarchy.
 *
 * Resolution order (fail closed; never recursive through ZELLE):
 * 1. EXACT_USDTTRC20_DESTINATION_PEER — USDTTRC20→same dest course (quoteable preferred;
 *    unique positive inactive peer accepted as market base when public peer absent)
 * 2. STABLECOIN_NETWORK_BENCHMARK — USDT/USDC network variants and USD-peg rails via assetFromCode
 *    using the same IndependentMarketBaseline / USDT peg policy already certified elsewhere
 * 3. CANONICAL_EXISTING_MARKET_CROSS_RATE — USD to fiat via IndependentMarketBaseline
 *    (e.g. CARDTHB via USDTHB)
 * 4. FAIL_CLOSED
 *
 * Never uses BestChange ZELLEUSD offers or Exswaping's own ZELLE listing as input.
 */
final class ZelleUsdBenchmarkResolver
{
    public const ZELLE_CURRENCY_ID = 87;
    public const USDTTRC20_CURRENCY_ID = 3;

    /** Max age of the USDTTRC20 peer course before ZELLE is treated stale (fail closed). */
    public const DEFAULT_MAX_AGE_SECONDS = 3600;

    public function __construct(
        private readonly int $maxAgeSeconds = self::DEFAULT_MAX_AGE_SECONDS,
        private readonly ?IndependentMarketBaseline $baseline = null,
    ) {
    }

    public static function make(): self
    {
        return new self();
    }

    public function isZelleOutgoing(DirectionExchange $direction): bool
    {
        return (int) ($direction->id_currency1 ?? 0) === self::ZELLE_CURRENCY_ID;
    }

    /** Crypto/fiat → ZELLEUSD (receive Zelle). */
    public function isZelleIncoming(DirectionExchange $direction): bool
    {
        return (int) ($direction->id_currency2 ?? 0) === self::ZELLE_CURRENCY_ID
            && (int) ($direction->id_currency1 ?? 0) > 0
            && (int) ($direction->id_currency1 ?? 0) !== self::ZELLE_CURRENCY_ID;
    }

    public function isZelleLeg(DirectionExchange $direction): bool
    {
        return $this->isZelleOutgoing($direction) || $this->isZelleIncoming($direction);
    }

    /**
     * Resolve BASE course for any Zelle leg.
     * Outgoing: USDTTRC20→dest hierarchy. Incoming: exact inverse of outgoing peer BASE.
     */
    public function resolve(DirectionExchange $zelleDirection): ZelleUsdBenchmarkResult
    {
        if ($this->isZelleIncoming($zelleDirection)) {
            return $this->resolveIncomingInverse($zelleDirection);
        }

        $zelleId = (int) ($zelleDirection->id ?? 0);
        if (!$this->isZelleOutgoing($zelleDirection)) {
            return new ZelleUsdBenchmarkResult(
                eligible: false,
                zelleDirectionId: $zelleId,
                destinationCurrencyId: null,
                benchmarkDirectionId: null,
                benchmarkRate: null,
                benchmarkTimestamp: null,
                benchmarkSource: null,
                fresh: false,
                reasonCode: ZelleUsdBenchmarkResult::REASON_NOT_ZELLE_LEG,
            );
        }

        $destId = (int) ($zelleDirection->id_currency2 ?? 0);
        if ($destId <= 0) {
            return $this->fail($zelleId, null, ZelleUsdBenchmarkResult::REASON_SOURCE_ERROR);
        }

        // Direct ZELLEUSD → USDTTRC20: explicit 1:1 USD stable peg (not a circular self-rate).
        if ($destId === self::USDTTRC20_CURRENCY_ID) {
            return new ZelleUsdBenchmarkResult(
                eligible: true,
                zelleDirectionId: $zelleId,
                destinationCurrencyId: $destId,
                benchmarkDirectionId: null,
                benchmarkRate: '1',
                benchmarkTimestamp: gmdate('c'),
                benchmarkSource: 'STABLE_USD_PEG',
                fresh: true,
                reasonCode: ZelleUsdBenchmarkResult::REASON_STABLE_PEG,
                matchMethod: 'stable_zelle_to_usdttrc20_policy',
            );
        }

        if (CurrencyPublicRetirement::isCurrencyIdRetired($destId)
            || CurrencyPublicRetirement::isCurrencyIdRetired(self::USDTTRC20_CURRENCY_ID)
        ) {
            return $this->fail($zelleId, $destId, ZelleUsdBenchmarkResult::REASON_RETIRED);
        }

        // Tier 1 — exact USDTTRC20 → destination peer.
        $tier1 = $this->resolveExactUsdtTrc20Peer($zelleId, $destId);
        if ($tier1 !== null) {
            return $tier1;
        }

        $destXml = $this->destinationXml($destId);

        // Tier 2 — stablecoin / USD-peg network benchmark (reuse IMB asset mapping).
        $tier2 = $this->resolveStablecoinNetworkBenchmark($zelleId, $destId, $destXml);
        if ($tier2 !== null) {
            return $tier2;
        }

        // Tier 3 — canonical USD→fiat cross-rate (card/bank rails).
        $tier3 = $this->resolveCanonicalUsdFiatCrossRate($zelleId, $destId, $destXml);
        if ($tier3 !== null) {
            return $tier3;
        }

        return $this->fail($zelleId, $destId, ZelleUsdBenchmarkResult::REASON_MISSING);
    }

    private function resolveExactUsdtTrc20Peer(int $zelleId, int $destId): ?ZelleUsdBenchmarkResult
    {
        try {
            $peers = DB::table('direction_exchange')
                ->where('id_currency1', self::USDTTRC20_CURRENCY_ID)
                ->where('id_currency2', $destId)
                ->whereNull('deleted_at')
                ->get(['id', 'status', 'course_value', 'updated_at', 'parser_source_name']);
        } catch (Throwable) {
            return $this->fail($zelleId, $destId, ZelleUsdBenchmarkResult::REASON_SOURCE_ERROR);
        }

        if ($peers->isEmpty()) {
            return null; // fall through
        }

        $positive = $peers->filter(function ($p) {
            $course = (string) ($p->course_value ?? '0');

            return is_numeric($course)
                && bccomp($course, '0', 18) > 0
                && !PublicDuplicateExclusion::isExcluded((int) $p->id);
        })->values();

        if ($positive->isEmpty()) {
            // Peers exist but all zero — do not block Tier 2/3 for stables/fiat.
            return null;
        }

        if ($positive->count() > 1) {
            // Prefer unique status=1 quoteable; if still multiple, fail closed (no silent pick).
            $quoteable = $positive->filter(fn ($p) => (int) $p->status === 1)->values();
            if ($quoteable->count() === 1) {
                $positive = $quoteable;
            } elseif ($quoteable->count() > 1) {
                return $this->fail($zelleId, $destId, ZelleUsdBenchmarkResult::REASON_AMBIGUOUS);
            } else {
                return $this->fail($zelleId, $destId, ZelleUsdBenchmarkResult::REASON_AMBIGUOUS);
            }
        }

        $peer = $positive->first();
        $rate = (string) $peer->course_value;
        $src = (string) ($peer->parser_source_name ?: 'USDTTRC20_PEER');
        if (stripos($src, 'ZELLE') !== false) {
            return $this->fail($zelleId, $destId, ZelleUsdBenchmarkResult::REASON_SOURCE_ERROR);
        }

        $ts = isset($peer->updated_at) ? (string) $peer->updated_at : null;
        if ($ts !== null && $ts !== '') {
            try {
                $age = time() - strtotime($ts.' UTC');
                if ($age > $this->maxAgeSeconds) {
                    // Stale peer: fall through to Tier 2/3 rather than hard-fail the catalog.
                    return null;
                }
            } catch (Throwable) {
                // keep going
            }
        }

        $active = (int) $peer->status === 1;

        return new ZelleUsdBenchmarkResult(
            eligible: true,
            zelleDirectionId: $zelleId,
            destinationCurrencyId: $destId,
            benchmarkDirectionId: (int) $peer->id,
            benchmarkRate: $rate,
            benchmarkTimestamp: $ts,
            benchmarkSource: $src,
            fresh: true,
            reasonCode: ZelleUsdBenchmarkResult::REASON_FOUND,
            matchMethod: $active ? 'currency_id_exact' : 'currency_id_exact_inactive_peer',
        );
    }

    private function resolveStablecoinNetworkBenchmark(int $zelleId, int $destId, ?string $destXml): ?ZelleUsdBenchmarkResult
    {
        if ($destXml === null || $destXml === '') {
            return null;
        }

        $asset = IndependentMarketBaseline::assetFromCode($destXml);
        if ($asset === null) {
            return null;
        }

        // Same-asset USDT networks (ERC20/BEP20/SOL/TON/…): certified USD stable peg.
        if ($asset === 'USDT' && (str_starts_with($destXml, 'USDT') || str_ends_with($destXml, 'USD'))) {
            // REVBUSD / ZELLE-like USD rails and USDT* networks share USDT peg policy.
            return new ZelleUsdBenchmarkResult(
                eligible: true,
                zelleDirectionId: $zelleId,
                destinationCurrencyId: $destId,
                benchmarkDirectionId: null,
                benchmarkRate: '1',
                benchmarkTimestamp: gmdate('c'),
                benchmarkSource: 'STABLE_USD_PEG',
                fresh: true,
                reasonCode: ZelleUsdBenchmarkResult::REASON_STABLE_PEG,
                matchMethod: 'stablecoin_network_usdt_peg',
            );
        }

        if ($asset === 'USDC' || str_starts_with($destXml, 'USDC')) {
            $baseline = $this->baseline ?? new IndependentMarketBaseline();
            $via = $baseline->cryptoViaUsdt('USDT', 'USDC');
            if ($via === null || !isset($via['rate']) || bccomp((string) $via['rate'], '0', 18) <= 0) {
                // Fall back to unity only when IMB has no fresh USDC quote — still fail closed
                // rather than invent a second engine; try raw USDCUSDT invert.
                $q = $baseline->quote('USDCUSDT');
                if ($q === null || bccomp((string) $q['rate'], '0', 18) <= 0) {
                    return null;
                }
                if ((int) ($q['age_seconds'] ?? PHP_INT_MAX) > IndependentMarketBaseline::CRYPTO_MAX_AGE_SECONDS) {
                    return null;
                }
                $rate = bcdiv('1', (string) $q['rate'], 18);

                return new ZelleUsdBenchmarkResult(
                    eligible: true,
                    zelleDirectionId: $zelleId,
                    destinationCurrencyId: $destId,
                    benchmarkDirectionId: null,
                    benchmarkRate: $rate,
                    benchmarkTimestamp: (string) ($q['as_of'] ?? gmdate('c')),
                    benchmarkSource: 'IMB_USDCUSDT:'.$q['source'],
                    fresh: true,
                    reasonCode: ZelleUsdBenchmarkResult::REASON_FOUND,
                    matchMethod: 'stablecoin_network_usdc_via_imb',
                );
            }

            if ((int) ($via['age_seconds'] ?? PHP_INT_MAX) > IndependentMarketBaseline::CRYPTO_MAX_AGE_SECONDS) {
                return null;
            }

            return new ZelleUsdBenchmarkResult(
                eligible: true,
                zelleDirectionId: $zelleId,
                destinationCurrencyId: $destId,
                benchmarkDirectionId: null,
                benchmarkRate: (string) $via['rate'],
                benchmarkTimestamp: (string) ($via['as_of'] ?? gmdate('c')),
                benchmarkSource: 'IMB_CRYPTO_VIA_USDT:'.$via['source'],
                fresh: true,
                reasonCode: ZelleUsdBenchmarkResult::REASON_FOUND,
                matchMethod: 'stablecoin_network_usdc_via_imb',
            );
        }

        // Network / BestChange-facing tickers mapped onto a traded crypto asset
        // (e.g. GRAM → TON): use the same IMB cryptoViaUsdt path already certified
        // for other market legs. Never invent a rate when IMB is missing/stale.
        if (!in_array($asset, ['USDT', 'USDC'], true)) {
            $baseline = $this->baseline ?? new IndependentMarketBaseline();
            $via = $baseline->cryptoViaUsdt('USDT', $asset);
            if ($via === null || !isset($via['rate']) || bccomp((string) $via['rate'], '0', 18) <= 0) {
                return null;
            }
            if ((int) ($via['age_seconds'] ?? PHP_INT_MAX) > IndependentMarketBaseline::CRYPTO_MAX_AGE_SECONDS) {
                return null;
            }

            return new ZelleUsdBenchmarkResult(
                eligible: true,
                zelleDirectionId: $zelleId,
                destinationCurrencyId: $destId,
                benchmarkDirectionId: null,
                benchmarkRate: (string) $via['rate'],
                benchmarkTimestamp: (string) ($via['as_of'] ?? gmdate('c')),
                benchmarkSource: 'IMB_CRYPTO_VIA_USDT:'.$via['source'],
                fresh: true,
                reasonCode: ZelleUsdBenchmarkResult::REASON_FOUND,
                matchMethod: 'network_crypto_via_imb_'.$asset,
            );
        }

        return null;
    }

    private function resolveCanonicalUsdFiatCrossRate(int $zelleId, int $destId, ?string $destXml): ?ZelleUsdBenchmarkResult
    {
        if ($destXml === null || $destXml === '') {
            return null;
        }

        $fiat = $this->fiatCodeFromDestinationXml($destXml);
        if ($fiat === null) {
            return null;
        }

        $symbol = 'USD'.$fiat;
        $baseline = $this->baseline ?? new IndependentMarketBaseline();
        $quote = $baseline->quote($symbol);
        if ($quote === null || !isset($quote['rate']) || bccomp((string) $quote['rate'], '0', 18) <= 0) {
            return null;
        }
        if ((int) ($quote['age_seconds'] ?? PHP_INT_MAX) > IndependentMarketBaseline::FIAT_MAX_AGE_SECONDS) {
            return null;
        }

        return new ZelleUsdBenchmarkResult(
            eligible: true,
            zelleDirectionId: $zelleId,
            destinationCurrencyId: $destId,
            benchmarkDirectionId: null,
            benchmarkRate: (string) $quote['rate'],
            benchmarkTimestamp: (string) ($quote['as_of'] ?? gmdate('c')),
            benchmarkSource: 'IMB_'.$symbol.':'.$quote['source'],
            fresh: true,
            reasonCode: ZelleUsdBenchmarkResult::REASON_FOUND,
            matchMethod: 'market_cross_usd_fiat_imb',
        );
    }

    private function fiatCodeFromDestinationXml(string $destXml): ?string
    {
        $xml = strtoupper(trim($destXml));
        // CARDTHB / CASHTHB / … → THB; known ISO suffixes used by active rails.
        foreach (['THB', 'IDR', 'CNY', 'INR', 'CAD', 'AED', 'GEL', 'KZT', 'BYN', 'AMD', 'UAH', 'RUB', 'UZS', 'TJS', 'KGS', 'EUR', 'USD'] as $fiat) {
            if ($xml === $fiat || str_ends_with($xml, $fiat)) {
                // Avoid treating USDT/USDC as fiat USD.
                if ($fiat === 'USD' && (str_contains($xml, 'USDT') || str_contains($xml, 'USDC'))) {
                    return null;
                }

                return $fiat;
            }
        }

        return null;
    }

    private function destinationXml(int $destId): ?string
    {
        try {
            $row = DB::table('currencies')->where('id', $destId)->value('designation_xml');
            if (is_string($row) && $row !== '') {
                return strtoupper($row);
            }
            $c = Currency::query()->find($destId);

            return $c ? strtoupper((string) $c->designation_xml) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Crypto→ZELLEUSD BASE = 1 / (ZELLEUSD→crypto BASE).
     * Uses the same outgoing resolver so both legs share one authority tree.
     */
    private function resolveIncomingInverse(DirectionExchange $incoming): ZelleUsdBenchmarkResult
    {
        $inId = (int) ($incoming->id ?? 0);
        $coinId = (int) ($incoming->id_currency1 ?? 0);
        if ($coinId <= 0) {
            return $this->fail($inId, null, ZelleUsdBenchmarkResult::REASON_SOURCE_ERROR);
        }

        try {
            $outRow = DB::table('direction_exchange')
                ->where('id_currency1', self::ZELLE_CURRENCY_ID)
                ->where('id_currency2', $coinId)
                ->whereNull('deleted_at')
                ->where('status', 1)
                ->orderByDesc('id')
                ->first();
        } catch (Throwable) {
            return $this->fail($inId, $coinId, ZelleUsdBenchmarkResult::REASON_SOURCE_ERROR);
        }

        if ($outRow === null) {
            return $this->fail($inId, $coinId, ZelleUsdBenchmarkResult::REASON_INBOUND_PEER_MISSING);
        }

        $outgoing = DirectionExchange::query()->find((int) $outRow->id);
        if ($outgoing === null) {
            return $this->fail($inId, $coinId, ZelleUsdBenchmarkResult::REASON_INBOUND_PEER_MISSING);
        }

        $outResolved = $this->resolve($outgoing);
        if (!$outResolved->eligible || $outResolved->benchmarkRate === null) {
            return new ZelleUsdBenchmarkResult(
                eligible: false,
                zelleDirectionId: $inId,
                destinationCurrencyId: $coinId,
                benchmarkDirectionId: (int) $outgoing->id,
                benchmarkRate: null,
                benchmarkTimestamp: $outResolved->benchmarkTimestamp,
                benchmarkSource: $outResolved->benchmarkSource,
                fresh: false,
                reasonCode: $outResolved->reasonCode,
                matchMethod: 'inbound_inverse_peer_unresolved',
            );
        }

        $outRate = trim((string) $outResolved->benchmarkRate);
        if ($outRate === '' || !is_numeric($outRate) || (float) $outRate <= 0.0) {
            return $this->fail($inId, $coinId, ZelleUsdBenchmarkResult::REASON_ZERO);
        }

        try {
            // High-precision inverse: inbound Zelle per 1 coin.
            $inverse = $this->bcInvert($outRate, 18);
        } catch (Throwable) {
            return $this->fail($inId, $coinId, ZelleUsdBenchmarkResult::REASON_SOURCE_ERROR);
        }

        if ($inverse === null || (float) $inverse <= 0.0) {
            return $this->fail($inId, $coinId, ZelleUsdBenchmarkResult::REASON_ZERO);
        }

        return new ZelleUsdBenchmarkResult(
            eligible: true,
            zelleDirectionId: $inId,
            destinationCurrencyId: $coinId,
            benchmarkDirectionId: (int) $outgoing->id,
            benchmarkRate: $inverse,
            benchmarkTimestamp: $outResolved->benchmarkTimestamp ?? gmdate('c'),
            benchmarkSource: 'INVERSE_OF_OUTGOING:'.((string) ($outResolved->benchmarkSource ?? 'OUT')),
            fresh: $outResolved->fresh,
            reasonCode: ZelleUsdBenchmarkResult::REASON_INBOUND_INVERSE,
            matchMethod: 'inbound_inverse_of_zelle_outgoing',
        );
    }

    private function bcInvert(string $rate, int $scale): ?string
    {
        if (!function_exists('bcdiv') || !function_exists('bccomp')) {
            $f = (float) $rate;
            if ($f <= 0.0) {
                return null;
            }

            return sprintf('%.'.$scale.'F', 1.0 / $f);
        }
        if (bccomp($rate, '0', $scale) !== 1) {
            return null;
        }

        return bcdiv('1', $rate, $scale);
    }

    private function fail(int $zelleId, ?int $destId, string $reason): ZelleUsdBenchmarkResult
    {
        return new ZelleUsdBenchmarkResult(
            eligible: false,
            zelleDirectionId: $zelleId,
            destinationCurrencyId: $destId,
            benchmarkDirectionId: null,
            benchmarkRate: null,
            benchmarkTimestamp: null,
            benchmarkSource: null,
            fresh: false,
            reasonCode: $reason,
        );
    }
}
