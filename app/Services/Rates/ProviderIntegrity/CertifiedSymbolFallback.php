<?php

declare(strict_types=1);

namespace App\Services\Rates\ProviderIntegrity;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Certified market-reference fallbacks when venue parsers are all ineligible.
 * Does not change direction ownership — only supplies an IMB asset-leg quote.
 */
final class CertifiedSymbolFallback
{
    public const SCALE = 18;

    /**
     * @param callable(string):(?array{rate:string,source:string,as_of:string,age_seconds:int})|null $quoteFn
     */
    public function __construct(
        private readonly mixed $quoteFn = null,
        private readonly ?array $fallbackConfig = null,
        private readonly ?string $agreementMaxDeviation = null,
    ) {
    }

    /**
     * @return array{
     *   ok:bool,
     *   rate:?string,
     *   quality_state:string,
     *   source:?string,
     *   as_of:?string,
     *   age_seconds:?int,
     *   provider_count:int,
     *   distinct_providers:list<string>,
     *   components:list<array<string,mixed>>,
     *   reason:?string
     * }|null
     */
    public function resolve(string $symbol): ?array
    {
        $symbol = strtoupper(trim($symbol));
        $specs = $this->fallbackConfig[$symbol]
            ?? (array) $this->cfg('symbol_fallbacks.' . $symbol, []);
        if ($specs === []) {
            // cfg nested path may not work — load full map
            $all = (array) $this->cfg('symbol_fallbacks', []);
            $specs = (array) ($all[$symbol] ?? []);
        }
        if ($specs === []) {
            return null;
        }

        $refs = [];
        foreach ($specs as $spec) {
            if (!is_array($spec)) {
                continue;
            }
            $ref = $this->resolveOne($spec);
            if ($ref !== null) {
                $refs[] = $ref;
            }
        }
        if ($refs === []) {
            return [
                'ok' => false,
                'rate' => null,
                'quality_state' => ProviderLegAggregator::QUALITY_QUARANTINED,
                'source' => null,
                'as_of' => null,
                'age_seconds' => null,
                'provider_count' => 0,
                'distinct_providers' => [],
                'components' => [],
                'reason' => 'fallback_refs_unavailable',
            ];
        }

        $rates = array_column($refs, 'rate');
        $median = $this->median($rates);
        $maxDev = $this->agreementMaxDeviation
            ?? (string) $this->cfg('fallback_agreement_max_deviation', '0.05');

        $agree = true;
        if (count($refs) >= 2 && $median !== null) {
            foreach ($rates as $r) {
                $dev = $this->absBc(bcdiv(bcsub($r, $median, self::SCALE), $median, self::SCALE));
                if (bccomp($dev, $maxDev, self::SCALE) === 1) {
                    $agree = false;
                    break;
                }
            }
        }

        $labels = array_map(static fn (array $r): string => (string) $r['label'], $refs);
        sort($labels);
        $quality = (count($refs) >= 2 && $agree)
            ? ProviderLegAggregator::QUALITY_HEALTHY
            : ProviderLegAggregator::QUALITY_DEGRADED_FALLBACK;

        return [
            'ok' => true,
            'rate' => $median,
            'quality_state' => $quality,
            'source' => 'certified_fallback:' . implode('+', $labels),
            'as_of' => max(array_column($refs, 'as_of')),
            'age_seconds' => max(array_column($refs, 'age_seconds')),
            'provider_count' => count($refs),
            'distinct_providers' => $labels,
            'components' => $refs,
            'reason' => $agree ? 'fallback_agree' : 'fallback_single_or_disagree',
        ];
    }

    /**
     * @param array<string,mixed> $spec
     * @return array{rate:string,label:string,as_of:string,age_seconds:int,source:string}|null
     */
    private function resolveOne(array $spec): ?array
    {
        $type = (string) ($spec['type'] ?? '');
        $label = (string) ($spec['label'] ?? $type);
        try {
            if ($type === 'direction_inverse_course') {
                $id = (int) ($spec['direction_id'] ?? 0);
                if ($id <= 0 || !class_exists(DB::class)) {
                    return null;
                }
                $row = DB::table('direction_exchange')
                    ->where('id', $id)
                    ->where('status', 1)
                    ->whereNull('deleted_at')
                    ->first(['course_value', 'updated_at']);
                if ($row === null) {
                    return null;
                }
                $course = (string) ($row->course_value ?? '0');
                if (bccomp($course, '0', self::SCALE) !== 1) {
                    return null;
                }
                $rate = bcdiv('1', $course, self::SCALE);
                $asOf = (string) ($row->updated_at ?? gmdate('Y-m-d H:i:s'));
                $age = max(0, time() - strtotime($asOf));

                return [
                    'rate' => $rate,
                    'label' => $label,
                    'as_of' => $asOf,
                    'age_seconds' => $age,
                    'source' => 'direction:' . $id . ':inverse',
                ];
            }

            if ($type === 'direction_btc_gram_cross') {
                $id = (int) ($spec['direction_id'] ?? 0);
                $btcSym = strtoupper((string) ($spec['btc_symbol'] ?? 'BTCUSDT'));
                if ($id <= 0 || !class_exists(DB::class)) {
                    return null;
                }
                $row = DB::table('direction_exchange')
                    ->where('id', $id)
                    ->where('status', 1)
                    ->whereNull('deleted_at')
                    ->first(['course_value', 'updated_at']);
                if ($row === null) {
                    return null;
                }
                $gramPerBtc = (string) ($row->course_value ?? '0');
                if (bccomp($gramPerBtc, '0', self::SCALE) !== 1) {
                    return null;
                }
                $btc = $this->quoteSymbol($btcSym);
                if ($btc === null || bccomp($btc['rate'], '0', self::SCALE) !== 1) {
                    return null;
                }
                $rate = bcdiv($btc['rate'], $gramPerBtc, self::SCALE);
                $asOf = max((string) ($row->updated_at ?? ''), $btc['as_of']);
                $age = max(0, time() - strtotime($asOf));

                return [
                    'rate' => $rate,
                    'label' => $label,
                    'as_of' => $asOf,
                    'age_seconds' => max($age, (int) $btc['age_seconds']),
                    'source' => 'direction:' . $id . ':btc_cross',
                ];
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @return array{rate:string,source:string,as_of:string,age_seconds:int}|null
     */
    private function quoteSymbol(string $symbol): ?array
    {
        if (is_callable($this->quoteFn)) {
            $q = ($this->quoteFn)($symbol);

            return is_array($q) ? $q : null;
        }

        // Avoid recursive fallback: ask IMB with fallbacks disabled via injected empty path not available.
        // Use parser BTCUSDT directly for cross.
        try {
            if (!class_exists(DB::class)) {
                return null;
            }
            $row = DB::table('parser_exchange')
                ->where('status', 1)
                ->where('code_in', 'BTC')
                ->where('code_out', 'USDT')
                ->orderByDesc('updated_at')
                ->first(['summa', 'updated_at', 'code']);
            if ($row === null) {
                return null;
            }
            $asOf = (string) ($row->updated_at ?? '');
            $age = $asOf !== '' ? max(0, time() - strtotime($asOf)) : PHP_INT_MAX;
            if ($age > 900) {
                return null;
            }

            return [
                'rate' => bcmul((string) $row->summa, '1', self::SCALE),
                'source' => 'parser_exchange:' . (string) ($row->code ?? 'btc'),
                'as_of' => $asOf,
                'age_seconds' => $age,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param list<string> $rates
     */
    private function median(array $rates): ?string
    {
        if ($rates === []) {
            return null;
        }
        $sorted = $rates;
        sort($sorted, SORT_STRING);
        $count = count($sorted);
        $mid = intdiv($count, 2);
        if ($count % 2 === 1) {
            return $sorted[$mid];
        }

        return bcdiv(bcadd($sorted[$mid - 1], $sorted[$mid], self::SCALE), '2', self::SCALE);
    }

    private function absBc(string $value): string
    {
        return bccomp($value, '0', self::SCALE) === -1
            ? bcmul($value, '-1', self::SCALE)
            : $value;
    }

    private function cfg(string $key, mixed $default): mixed
    {
        if (function_exists('config')) {
            return config('rates_provider_integrity.' . $key, $default);
        }
        $path = dirname(__DIR__, 3) . '/config/rates_provider_integrity.php';
        if (!is_file($path)) {
            return $default;
        }
        $all = include $path;
        if (!is_array($all)) {
            return $default;
        }
        if (!str_contains($key, '.')) {
            return $all[$key] ?? $default;
        }
        $cur = $all;
        foreach (explode('.', $key) as $part) {
            if (!is_array($cur) || !array_key_exists($part, $cur)) {
                return $default;
            }
            $cur = $cur[$part];
        }

        return $cur;
    }
}
