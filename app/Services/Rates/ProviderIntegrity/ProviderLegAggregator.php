<?php

declare(strict_types=1);

namespace App\Services\Rates\ProviderIntegrity;

/**
 * Aggregate distinct eligible providers into a robust median with quality state.
 */
final class ProviderLegAggregator
{
    public const QUALITY_HEALTHY = 'HEALTHY';

    public const QUALITY_DEGRADED_SINGLE = 'DEGRADED_SINGLE_PROVIDER';

    public const QUALITY_DEGRADED_FALLBACK = 'DEGRADED_FALLBACK';

    public const QUALITY_QUARANTINED = 'QUARANTINED';

    public const SCALE = 18;

    public function __construct(
        private readonly ProviderQuoteClassifier $classifier = new ProviderQuoteClassifier(),
        private readonly ?string $outlierMaxDeviation = null,
        private readonly ?int $minDistinctForHealthy = null,
        private readonly ?bool $allowSingle = null,
    ) {
    }

    /**
     * @param list<array{
     *   code:string,
     *   summa:string,
     *   status:int,
     *   age_seconds:int,
     *   as_of:string,
     *   code_in?:string,
     *   code_out?:string,
     *   book_available?:bool|null,
     *   kline_available?:bool|null
     * }> $rows
     * @return array{
     *   ok:bool,
     *   rate:?string,
     *   quality_state:string,
     *   provider_count:int,
     *   distinct_providers:list<string>,
     *   source:?string,
     *   as_of:?string,
     *   age_seconds:?int,
     *   divergence:?string,
     *   excluded:list<array<string,mixed>>,
     *   used:list<array<string,mixed>>,
     *   reason:?string
     * }
     */
    public function aggregate(array $rows, int $maxAgeSeconds): array
    {
        $outlierMax = $this->outlierMaxDeviation
            ?? (string) $this->cfg('outlier_max_deviation', '0.08');
        $minHealthy = $this->minDistinctForHealthy
            ?? (int) $this->cfg('min_distinct_providers_for_healthy', 2);
        $allowSingle = $this->allowSingle
            ?? (bool) $this->cfg('allow_single_provider', true);

        $excluded = [];
        /** @var array<string, array{rate:string,source:string,as_of:string,age_seconds:int,provider:string}> $byProvider */
        $byProvider = [];

        foreach ($rows as $row) {
            $class = $this->classifier->classify($row, $maxAgeSeconds);
            if (!$class['eligible']) {
                $excluded[] = $class + [
                    'code' => (string) ($row['code'] ?? ''),
                    'summa' => (string) ($row['summa'] ?? ''),
                ];
                continue;
            }
            $provider = $class['provider'];
            $rate = bcmul((string) $row['summa'], '1', self::SCALE);
            // One vote per provider (dedupe identical binance_ton-usdt rows).
            if (!isset($byProvider[$provider])
                || (int) $row['age_seconds'] < (int) $byProvider[$provider]['age_seconds']) {
                $byProvider[$provider] = [
                    'rate' => $rate,
                    'source' => 'parser_exchange:' . (string) ($row['code'] ?? $provider),
                    'as_of' => (string) ($row['as_of'] ?? ''),
                    'age_seconds' => (int) $row['age_seconds'],
                    'provider' => $provider,
                ];
            }
        }

        if ($byProvider === []) {
            return [
                'ok' => false,
                'rate' => null,
                'quality_state' => self::QUALITY_QUARANTINED,
                'provider_count' => 0,
                'distinct_providers' => [],
                'source' => null,
                'as_of' => null,
                'age_seconds' => null,
                'divergence' => null,
                'excluded' => $excluded,
                'used' => [],
                'reason' => 'no_eligible_providers',
            ];
        }

        // Outlier rejection vs robust median of distinct providers.
        $rates = array_column(array_values($byProvider), 'rate');
        $median = $this->median($rates);
        $kept = [];
        foreach ($byProvider as $provider => $q) {
            if ($median !== null && count($byProvider) >= 2) {
                $dev = $this->absBc(bcdiv(bcsub($q['rate'], $median, self::SCALE), $median, self::SCALE));
                if (bccomp($dev, $outlierMax, self::SCALE) === 1) {
                    $excluded[] = [
                        'class' => ProviderQuoteClassifier::OUTLIER,
                        'eligible' => false,
                        'provider' => $provider,
                        'symbol' => '',
                        'exchange_status' => '',
                        'reason' => 'provider_outlier:' . $dev,
                        'code' => $q['source'],
                        'summa' => $q['rate'],
                    ];
                    continue;
                }
            }
            $kept[$provider] = $q;
        }

        if ($kept === []) {
            return [
                'ok' => false,
                'rate' => null,
                'quality_state' => self::QUALITY_QUARANTINED,
                'provider_count' => 0,
                'distinct_providers' => [],
                'source' => null,
                'as_of' => null,
                'age_seconds' => null,
                'divergence' => null,
                'excluded' => $excluded,
                'used' => [],
                'reason' => 'all_providers_outliers',
            ];
        }

        $keptRates = array_column(array_values($kept), 'rate');
        $finalMedian = $this->median($keptRates);
        $count = count($kept);
        $providers = array_keys($kept);
        sort($providers);

        $divergence = null;
        if ($count >= 2 && $finalMedian !== null) {
            $sorted = $keptRates;
            sort($sorted, SORT_STRING);
            $divergence = bcdiv(
                bcsub($sorted[count($sorted) - 1], $sorted[0], self::SCALE),
                $finalMedian,
                self::SCALE
            );
        }

        $best = array_values($kept)[0];
        foreach ($kept as $q) {
            if ($finalMedian !== null && bccomp($q['rate'], $finalMedian, 8) === 0) {
                $best = $q;
                break;
            }
        }

        $quality = self::QUALITY_HEALTHY;
        $reason = 'median';
        if ($count < $minHealthy) {
            if (!$allowSingle) {
                return [
                    'ok' => false,
                    'rate' => null,
                    'quality_state' => self::QUALITY_QUARANTINED,
                    'provider_count' => $count,
                    'distinct_providers' => $providers,
                    'source' => null,
                    'as_of' => null,
                    'age_seconds' => null,
                    'divergence' => $divergence,
                    'excluded' => $excluded,
                    'used' => array_values($kept),
                    'reason' => 'single_provider_not_allowed',
                ];
            }
            $quality = self::QUALITY_DEGRADED_SINGLE;
            $reason = 'single_provider';
        }

        return [
            'ok' => true,
            'rate' => $finalMedian,
            'quality_state' => $quality,
            'provider_count' => $count,
            'distinct_providers' => $providers,
            'source' => $best['source'] . ($count > 1 ? '+median' : ''),
            'as_of' => $best['as_of'],
            'age_seconds' => min(array_column(array_values($kept), 'age_seconds')),
            'divergence' => $divergence,
            'excluded' => $excluded,
            'used' => array_values($kept),
            'reason' => $reason,
        ];
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

        return is_array($all) ? ($all[$key] ?? $default) : $default;
    }
}
