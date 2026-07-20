<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DirectionExchange;
use App\Services\Rates\IndependentMarketBaseline;
use App\Services\Rates\RateConfiguredExpectation;
use App\Services\Rates\RateSanityGuard;
use App\Services\Rates\RubFamilyPremiumPolicy;
use Illuminate\Console\Command;

/**
 * Direction-level economic validation across the active catalog.
 *
 * Classifications:
 * PASS | PASS_EXPLAINED_SPREAD | REVIEW | QUARANTINE_REQUIRED | NO_BASELINE
 */
final class RatesEconomicAuditCommand extends Command
{
    protected $signature = 'rates:economic-audit
        {--format=json : json|table}
        {--limit=0 : Limit directions (0 = all active)}';

    protected $description = 'Economic validation of all active directions against independent baselines.';

    public function handle(
        RateSanityGuard $guard,
        RateConfiguredExpectation $expectation,
        IndependentMarketBaseline $baseline,
    ): int {
        $limit = max(0, (int) $this->option('limit'));
        $policy = RubFamilyPremiumPolicy::fromStorageApp();
        $query = DirectionExchange::query()
            ->with(['currency1:id,designation_xml', 'currency2:id,designation_xml'])
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->orderBy('id');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $totals = [
            'reviewed' => 0,
            'PASS' => 0,
            'PASS_EXPLAINED_SPREAD' => 0,
            'REVIEW' => 0,
            'QUARANTINE_REQUIRED' => 0,
            'NO_BASELINE' => 0,
        ];
        $noBaselineFamilies = [];
        $rows = [];

        foreach ($query->cursor() as $direction) {
            $totals['reviewed']++;
            $from = strtoupper((string) ($direction->currency1?->designation_xml ?? ''));
            $to = strtoupper((string) ($direction->currency2?->designation_xml ?? ''));
            $family = $this->familyKey($from, $to);
            $course = $guard->normalize((string) ($direction->course_value ?? ''));
            $profit = (string) ($direction->profit ?? '0');
            $ind = $this->independentBaseline($baseline, $from, $to);

            if ($course === null) {
                $class = 'QUARANTINE_REQUIRED';
                $unexplained = null;
                $raw = null;
            } elseif ($ind === null) {
                $class = 'NO_BASELINE';
                $unexplained = null;
                $raw = null;
                $noBaselineFamilies[$family] = ($noBaselineFamilies[$family] ?? 0) + 1;
            } else {
                $paymentPremium = '0';
                if ($policy->isApproved() && str_contains($to, 'RUB')) {
                    $explained = $policy->explainedPremiumPercent($to);
                    if ($explained !== null) {
                        // Family premium is documented commercial band; do not double-count direction.profit.
                        $paymentPremium = (string) max(0.0, $explained - (float) $profit);
                    }
                }
                $analysis = $expectation->analyze(
                    baseline: $ind['rate'],
                    actual: $course,
                    profitPercent: $profit,
                    paymentSystemFeePercent: $paymentPremium,
                );
                $unexplained = $analysis['unexplained_deviation'];
                $raw = $analysis['raw_market_deviation'];
                $abs = $unexplained === null ? null : abs((float) $unexplained);
                $thresholds = $policy->thresholdsForDestination($to);
                if ($abs === null) {
                    $class = 'NO_BASELINE';
                    $noBaselineFamilies[$family] = ($noBaselineFamilies[$family] ?? 0) + 1;
                } elseif ($abs <= 1.0 && $raw !== null && abs((float) $raw) > 1.0) {
                    $class = 'PASS_EXPLAINED_SPREAD';
                } elseif ($abs <= $thresholds['pass']) {
                    $class = 'PASS';
                } elseif ($abs <= $thresholds['review']) {
                    $class = 'REVIEW';
                } else {
                    $class = 'QUARANTINE_REQUIRED';
                }
            }

            $totals[$class] = ($totals[$class] ?? 0) + 1;
            $rows[] = [
                'direction_id' => (int) $direction->id,
                'from' => $from,
                'to' => $to,
                'family' => $family,
                'course_value' => $direction->course_value,
                'profit' => $profit,
                'baseline' => $ind['rate'] ?? null,
                'baseline_source' => $ind['source'] ?? null,
                'baseline_path' => $ind['path'] ?? null,
                'raw_market_deviation' => $raw,
                'unexplained_deviation' => $unexplained,
                'classification' => $class,
                'source' => (string) ($direction->parser_source_name ?? ''),
                'allow_export' => (int) $direction->allow_export,
                'rub_policy_approved' => $policy->isApproved(),
            ];
        }

        arsort($noBaselineFamilies);
        $payload = [
            'generated_at' => now()->toIso8601String(),
            'rub_family_policy' => $policy->summary(),
            'totals' => $totals,
            'no_baseline_by_family' => $noBaselineFamilies,
            'directions' => $rows,
        ];

        if ((string) $this->option('format') === 'table') {
            $this->table(['metric', 'count'], collect($totals)->map(fn ($v, $k) => [$k, $v])->values()->all());
            $this->table(
                ['no_baseline_family', 'count'],
                collect($noBaselineFamilies)->map(fn ($v, $k) => [$k, $v])->values()->all()
            );
        } else {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }

    /**
     * @return array{rate:string,source:string,path?:string}|null
     */
    private function independentBaseline(IndependentMarketBaseline $baseline, string $from, string $to): ?array
    {
        $fromAsset = IndependentMarketBaseline::assetFromCode($from);
        $toAsset = IndependentMarketBaseline::assetFromCode($to);

        if (str_contains($to, 'RUB')) {
            if ($fromAsset === 'USDT' || $fromAsset === 'USDC') {
                $q = $baseline->quote('USDRUB');

                return $q ? ['rate' => $q['rate'], 'source' => $q['source'], 'path' => 'stable_to_rub'] : null;
            }
            if ($fromAsset !== null) {
                $q = $baseline->cryptoRub($fromAsset);

                return $q ? [
                    'rate' => $q['rate'],
                    'source' => $q['source'],
                    'path' => 'crypto_to_rub',
                ] : null;
            }
        }

        if (str_contains($to, 'GEL') && $fromAsset !== null && !in_array($fromAsset, ['USDT', 'USDC'], true)) {
            $q = $baseline->cryptoGel($fromAsset);

            return $q ? ['rate' => $q['rate'], 'source' => $q['source'], 'path' => 'crypto_to_gel'] : null;
        }

        if ($fromAsset !== null && $toAsset !== null) {
            $q = $baseline->cryptoViaUsdt($fromAsset, $toAsset);

            return $q ? [
                'rate' => $q['rate'],
                'source' => $q['source'],
                'path' => $q['path'] ?? 'crypto_via_usdt',
            ] : null;
        }

        return null;
    }

    private function familyKey(string $from, string $to): string
    {
        $fromAsset = IndependentMarketBaseline::assetFromCode($from) ?? 'OTHER';
        if (str_contains($to, 'RUB')) {
            return 'crypto_rub:' . $fromAsset;
        }
        if (str_contains($to, 'GEL')) {
            return 'crypto_gel:' . $fromAsset;
        }
        $toAsset = IndependentMarketBaseline::assetFromCode($to);
        if ($fromAsset !== 'OTHER' && $toAsset !== null) {
            return 'crypto_crypto:' . $fromAsset . '_to_' . $toAsset;
        }
        if ($fromAsset !== 'OTHER') {
            return 'source_asset:' . $fromAsset . '_uncovered_dest';
        }

        return 'unsupported_or_internal:' . ($from !== '' ? $from : 'UNKNOWN');
    }
}
