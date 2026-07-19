<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DirectionExchange;
use App\Services\Rates\BestChangeCurrencyCatalogGuard;
use App\Services\Rates\IndependentMarketBaseline;
use App\Services\Rates\RateConfiguredExpectation;
use App\Services\Rates\RateExportQuarantine;
use App\Services\Rates\RateSanityGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only catalog audit for published exchange rates.
 *
 * Distinguishes raw market deviation from unexplained deviation after
 * applying configured profit / fees exactly once.
 */
final class RatesAuditCommand extends Command
{
    protected $signature = 'rates:audit
        {--all : Audit every active direction}
        {--dry-run : Alias for default read-only behaviour (always true)}
        {--format=table : table|json}
        {--max-deviation=0.12 : Unused legacy flag; classification uses unexplained thresholds}
        {--limit=0 : Limit number of directions (0 = no limit)}
        {--gel-only : Only directions involving GEL / CARDGEL}';

    protected $description = 'Dry-run audit of active exchange directions (configured vs unexplained deviation).';

    public function handle(
        RateSanityGuard $guard,
        RateConfiguredExpectation $expectation,
        RateExportQuarantine $quarantine,
        IndependentMarketBaseline $baseline,
    ): int {
        $format = (string) $this->option('format');
        $limit = max(0, (int) $this->option('limit'));
        $gelOnly = (bool) $this->option('gel-only');

        $query = DirectionExchange::query()
            ->with(['currency1:id,designation_xml,tech_name', 'currency2:id,designation_xml,tech_name', 'bestchange_directions'])
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->orderBy('id');

        if ($gelOnly) {
            $query->where(function ($q) {
                $q->whereHas('currency1', fn ($c) => $c->where('designation_xml', 'like', '%GEL%'))
                    ->orWhereHas('currency2', fn ($c) => $c->where('designation_xml', 'like', '%GEL%'));
            });
        }

        if ($limit > 0) {
            $query->limit($limit);
        } elseif (!$this->option('all') && !$gelOnly) {
            $query->whereHas('currency2', fn ($c) => $c->where('designation_xml', 'CARDGEL'));
        }

        $catalogGuard = BestChangeCurrencyCatalogGuard::fromStorageApp();

        $rows = [];
        $totals = [
            'audited' => 0,
            'normal' => 0,
            'warning' => 0,
            'high' => 0,
            'critical' => 0,
            'extreme' => 0,
            'no_baseline' => 0,
            'invalid' => 0,
            'configured_spread' => 0,
            'export_blocked' => 0,
        ];
        $rawBuckets = [
            'normal' => 0,
            'warning' => 0,
            'high' => 0,
            'critical' => 0,
            'extreme' => 0,
            'no_baseline' => 0,
            'invalid' => 0,
        ];

        foreach ($query->cursor() as $direction) {
            /** @var DirectionExchange $direction */
            $totals['audited']++;
            $from = (string) ($direction->currency1?->designation_xml ?? '');
            $to = (string) ($direction->currency2?->designation_xml ?? '');
            $course = (string) ($direction->course_value ?? '0');
            $bc = $this->resolveBestChangeRow((int) $direction->id, $to);
            $bcRate = $bc ? (string) ($bc->rate_value ?? '0') : null;
            $profit = (string) ($direction->profit ?? '0');
            $source = (string) ($direction->parser_source_name ?? '');

            $normalizedCourse = $guard->normalize($course);
            if ($normalizedCourse === null) {
                $severity = 'invalid';
                $totals['invalid']++;
                $rawBuckets['invalid']++;
                $q = $quarantine->evaluate($course);
                if (!$q['allowed']) {
                    $totals['export_blocked']++;
                }
                $rows[] = [
                    'id' => $direction->id,
                    'from' => $from,
                    'to' => $to,
                    'source' => $source,
                    'course_value' => $course,
                    'bc_rate_value' => $bcRate,
                    'profit_percent' => $profit,
                    'baseline' => null,
                    'expected_final_rate' => null,
                    'raw_market_deviation' => null,
                    'expected_configured_deviation' => null,
                    'unexplained_deviation' => null,
                    'severity' => $severity,
                    'severity_basis' => 'invalid_course',
                    'export_status' => $q['status'],
                    'breakdown' => null,
                    'mapping' => null,
                    'allow_export' => $direction->allow_export,
                    'updated_at' => (string) $direction->updated_at,
                ];
                continue;
            }

            $mapping = null;
            if ($bc && isset($bc->id_currency_out)) {
                // Prefer the out-code claimed by the BestChange direction name, then
                // cross-check it against the local destination designation.
                $bcOutFromName = null;
                if (is_string($bc->name ?? null) && preg_match('/->\s*\[([A-Za-z0-9]+)\]/', (string) $bc->name, $m) === 1) {
                    $bcOutFromName = strtoupper($m[1]);
                }
                $mapping = $catalogGuard->validateId((int) $bc->id_currency_out, $bcOutFromName);
                if (($mapping['ok'] ?? true) === true && $bcOutFromName !== null && $to !== '' && $bcOutFromName !== strtoupper($to)) {
                    $mapping = [
                        'ok' => false,
                        'reason' => 'currency_mapping_mismatch',
                        'catalog_name' => $mapping['catalog_name'] ?? null,
                        'code_name' => $bcOutFromName,
                        'code' => $bcOutFromName,
                        'detail' => 'bestchange_out_code_differs_from_direction_destination',
                    ];
                }
            }

            $peerBaseline = ($bcRate && $guard->normalize($bcRate)) ? $guard->normalize($bcRate) : null;
            // Prefer live BC peer when BC status is active; otherwise fall back to independent.
            $useBc = $peerBaseline !== null && (int) ($bc->status ?? 0) === 1;
            $baselineRate = $useBc ? $peerBaseline : null;
            $baselineType = $useBc ? 'bestchange_rate_value' : null;

            if ($baselineRate === null) {
                $ind = $this->independentBaselineForPair($baseline, $from, $to);
                if ($ind !== null) {
                    $baselineRate = $ind['rate'];
                    $baselineType = $ind['source'];
                }
            }

            $analysis = $expectation->analyze(
                baseline: $baselineRate,
                actual: $normalizedCourse,
                profitPercent: $profit,
            );

            $forceBlock = null;
            if (is_array($mapping) && ($mapping['ok'] ?? true) === false) {
                $forceBlock = 'currency_mapping_mismatch';
            }

            $q = $quarantine->evaluate($normalizedCourse, [
                'baseline' => $baselineRate,
                'profit_percent' => $profit,
                'force_block_reason' => $forceBlock,
            ]);
            if (!$q['allowed']) {
                $totals['export_blocked']++;
            }

            $unexplained = $analysis['unexplained_deviation'];
            $raw = $analysis['raw_market_deviation'];

            $profitAbs = is_numeric($profit) ? abs((float) $profit) : 0.0;

            if ($forceBlock !== null) {
                $severity = 'extreme';
                $severityBasis = 'currency_mapping_mismatch';
            } elseif ($baselineRate === null) {
                $severity = 'no_baseline';
                $severityBasis = 'no_baseline';
            } elseif ($unexplained !== null && abs($unexplained) <= 1.0 && $raw !== null && abs($raw) > 1.0) {
                $severity = 'normal';
                $severityBasis = 'configured_spread';
                $totals['configured_spread']++;
            } elseif (
                $raw !== null
                && $profitAbs >= 1.0
                && $raw <= 1.0
                && $raw >= -($profitAbs + 1.5)
            ) {
                // Final rate is inside the intended commercial band vs market
                // (may be better for the customer than exact profit application).
                $severity = 'normal';
                $severityBasis = 'configured_spread_band';
                $totals['configured_spread']++;
            } else {
                $severity = $expectation->classifyUnexplained($unexplained);
                $severityBasis = 'unexplained_deviation';
            }

            $totals[$severity] = ($totals[$severity] ?? 0) + 1;
            $rawSeverity = $raw === null ? 'no_baseline' : $expectation->classifyUnexplained($raw);
            $rawBuckets[$rawSeverity] = ($rawBuckets[$rawSeverity] ?? 0) + 1;

            $rows[] = [
                'id' => $direction->id,
                'from' => $from,
                'to' => $to,
                'source' => $source,
                'course_value' => $course,
                'bc_rate_value' => $bcRate,
                'profit_percent' => $profit,
                'baseline' => $baselineRate,
                'baseline_type' => $baselineType,
                'expected_final_rate' => $analysis['expected_final_rate'],
                'raw_market_deviation' => $raw,
                'expected_configured_deviation' => $analysis['expected_configured_deviation'],
                'unexplained_deviation' => $unexplained,
                'severity' => $severity,
                'severity_basis' => $severityBasis,
                'export_status' => $q['status'],
                'export_reason' => $q['reason'],
                'breakdown' => $analysis,
                'mapping' => $mapping,
                'allow_export' => $direction->allow_export,
                'bc_status' => $bc->status ?? null,
                'bc_position_num' => $bc->position_num ?? null,
                'updated_at' => (string) $direction->updated_at,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return abs((float) ($b['unexplained_deviation'] ?? $b['raw_market_deviation'] ?? 0))
                <=> abs((float) ($a['unexplained_deviation'] ?? $a['raw_market_deviation'] ?? 0));
        });

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'dry_run' => true,
            'totals' => $totals,
            'raw_market_deviation_counts' => $rawBuckets,
            'unexplained_deviation_counts' => [
                'normal' => $totals['normal'],
                'warning' => $totals['warning'],
                'high' => $totals['high'],
                'critical' => $totals['critical'],
                'extreme' => $totals['extreme'],
                'no_baseline' => $totals['no_baseline'],
                'invalid' => $totals['invalid'],
                'configured_spread' => $totals['configured_spread'],
            ],
            'independent_baseline_coverage' => $baseline->coverage(),
            'directions' => $rows,
        ];

        if ($format === 'json') {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('rates:audit dry-run (unexplained-deviation primary)');
        $this->table(
            ['metric', 'count'],
            collect($totals)->map(fn ($v, $k) => [$k, $v])->values()->all()
        );

        return self::SUCCESS;
    }

    /**
     * @return array{rate:string,source:string}|null
     */
    private function independentBaselineForPair(IndependentMarketBaseline $baseline, string $from, string $to): ?array
    {
        $fromU = strtoupper($from);
        $toU = strtoupper($to);

        if (str_contains($toU, 'GEL')) {
            foreach (['BTC', 'ETH', 'BNB', 'TRX', 'TON'] as $asset) {
                if (str_starts_with($fromU, $asset)) {
                    $q = $baseline->cryptoGel($asset);
                    if ($q !== null) {
                        return ['rate' => $q['rate'], 'source' => $q['source']];
                    }
                }
            }
            if (str_starts_with($fromU, 'USDT') || str_starts_with($fromU, 'USDC')) {
                $q = $baseline->quote('USDGEL');
                if ($q !== null) {
                    return ['rate' => $q['rate'], 'source' => $q['source']];
                }
            }
        }

        // Stablecoin → USD-like receive near 1
        if ((str_starts_with($fromU, 'USDT') || str_starts_with($fromU, 'USDC'))
            && (str_contains($toU, 'USD') || $toU === 'PRUSD' || $toU === 'ZELLEUSD')) {
            return ['rate' => '1', 'source' => 'stablecoin_usd_parity'];
        }

        // ZELLEUSD give: customer pays USD via Zelle; course is units of receive per 1 USD.
        if ($fromU === 'ZELLEUSD') {
            if (str_starts_with($toU, 'USDT') || str_starts_with($toU, 'USDC')) {
                return ['rate' => '1', 'source' => 'zelle_usd_stablecoin_parity'];
            }
            foreach (['BTC', 'ETH', 'BNB', 'TRX', 'TON', 'XRP', 'LTC', 'DASH', 'SOL'] as $asset) {
                if (str_starts_with($toU, $asset)) {
                    $q = $baseline->quote($asset . 'USDT');
                    if ($q === null && $asset === 'XRP') {
                        // optional gap
                        break;
                    }
                    if ($q !== null && bccomp($q['rate'], '0', 18) === 1) {
                        return [
                            'rate' => bcdiv('1', $q['rate'], 18),
                            'source' => 'zelle_usd_per_crypto:' . $q['source'],
                        ];
                    }
                }
            }
            // Fiat receive: USD→fiat
            foreach (['RUB' => 'USDRUB', 'AMD' => 'USDAMD', 'EUR' => 'USDEUR', 'GEL' => 'USDGEL', 'KZT' => null, 'UAH' => null, 'CNY' => null, 'INR' => null, 'KGS' => null, 'TJS' => null, 'UZS' => null] as $fiat => $sym) {
                if (str_contains($toU, $fiat) && $sym !== null) {
                    $q = $baseline->quote($sym);
                    if ($q !== null) {
                        return ['rate' => $q['rate'], 'source' => 'zelle_usd_fiat:' . $q['source']];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Prefer the BestChange row whose destination code matches the direction.
     * Avoids HasOne collisions when multiple BC rows share one direction id.
     */
    private function resolveBestChangeRow(int $directionId, string $toCode): ?object
    {
        $rows = DB::table('bestchange_directions')
            ->where('id_direction_exchange', $directionId)
            ->orderByDesc('status')
            ->orderByDesc('updated_at')
            ->get();

        $to = strtoupper($toCode);
        foreach ($rows as $row) {
            if (is_string($row->name) && preg_match('/->\s*\[([A-Za-z0-9]+)\]/', $row->name, $m) === 1) {
                if (strtoupper($m[1]) === $to) {
                    return $row;
                }
            }
        }

        return $rows->first();
    }
}
