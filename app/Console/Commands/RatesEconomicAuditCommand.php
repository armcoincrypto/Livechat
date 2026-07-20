<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DirectionExchange;
use App\Services\Rates\IndependentMarketBaseline;
use App\Services\Rates\RateConfiguredExpectation;
use App\Services\Rates\RateSanityGuard;
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
        $rows = [];

        foreach ($query->cursor() as $direction) {
            $totals['reviewed']++;
            $from = strtoupper((string) ($direction->currency1?->designation_xml ?? ''));
            $to = strtoupper((string) ($direction->currency2?->designation_xml ?? ''));
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
            } else {
                $analysis = $expectation->analyze(
                    baseline: $ind['rate'],
                    actual: $course,
                    profitPercent: $profit,
                );
                $unexplained = $analysis['unexplained_deviation'];
                $raw = $analysis['raw_market_deviation'];
                $abs = $unexplained === null ? null : abs((float) $unexplained);
                if ($abs === null) {
                    $class = 'NO_BASELINE';
                } elseif ($abs <= 1.0 && $raw !== null && abs((float) $raw) > 1.0) {
                    $class = 'PASS_EXPLAINED_SPREAD';
                } elseif ($abs <= 3.0) {
                    $class = 'PASS';
                } elseif ($abs <= 7.0) {
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
                'course_value' => $direction->course_value,
                'profit' => $profit,
                'baseline' => $ind['rate'] ?? null,
                'baseline_source' => $ind['source'] ?? null,
                'raw_market_deviation' => $raw,
                'unexplained_deviation' => $unexplained,
                'classification' => $class,
                'source' => (string) ($direction->parser_source_name ?? ''),
                'allow_export' => (int) $direction->allow_export,
            ];
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'totals' => $totals,
            'directions' => $rows,
        ];

        if ((string) $this->option('format') === 'table') {
            $this->table(['metric', 'count'], collect($totals)->map(fn ($v, $k) => [$k, $v])->values()->all());
        } else {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }

    /**
     * @return array{rate:string,source:string}|null
     */
    private function independentBaseline(IndependentMarketBaseline $baseline, string $from, string $to): ?array
    {
        if (str_contains($to, 'RUB')) {
            if (str_starts_with($from, 'USDT') || str_starts_with($from, 'USDC')) {
                $q = $baseline->quote('USDRUB');

                return $q ? ['rate' => $q['rate'], 'source' => $q['source']] : null;
            }
            foreach (['BTC', 'ETH', 'BNB', 'TRX', 'TON', 'ZEC', 'LTC'] as $asset) {
                if ($from === $asset || str_starts_with($from, $asset)) {
                    $q = $baseline->cryptoRub($asset);

                    return $q ? ['rate' => $q['rate'], 'source' => $q['source']] : null;
                }
            }
        }
        if (str_contains($to, 'GEL')) {
            foreach (['BTC', 'ETH', 'BNB', 'TRX', 'TON'] as $asset) {
                if (str_starts_with($from, $asset)) {
                    $q = $baseline->cryptoGel($asset);

                    return $q ? ['rate' => $q['rate'], 'source' => $q['source']] : null;
                }
            }
        }

        return null;
    }
}
