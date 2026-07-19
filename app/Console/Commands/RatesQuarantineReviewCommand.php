<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rates\BestChangeCurrencyCatalogGuard;
use App\Services\Rates\IndependentMarketBaseline;
use App\Services\Rates\RateConfiguredExpectation;
use App\Services\Rates\RateExportQuarantine;
use App\Services\Rates\RateSanityGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Dry-run (default) review of quarantined directions for safe re-enablement.
 */
final class RatesQuarantineReviewCommand extends Command
{
    protected $signature = 'rates:quarantine-review
        {--dry-run : Read-only evaluation (default true)}
        {--format=json : json|table}
        {--limit=0 : Limit directions}';

    protected $description = 'Evaluate quarantined directions for READY_TO_ENABLE (dry-run by default).';

    public function handle(
        RateSanityGuard $guard,
        RateConfiguredExpectation $expectation,
        RateExportQuarantine $quarantine,
        IndependentMarketBaseline $baseline,
    ): int {
        $limit = max(0, (int) $this->option('limit'));
        $catalog = BestChangeCurrencyCatalogGuard::fromStorageApp();

        $q = DB::table('direction_exchange as d')
            ->join('currencies as c1', 'c1.id', '=', 'd.id_currency1')
            ->join('currencies as c2', 'c2.id', '=', 'd.id_currency2')
            ->where('d.status', 0)
            ->where('d.allow_export', 2)
            ->whereNull('d.deleted_at')
            ->orderBy('d.id');
        if ($limit > 0) {
            $q->limit($limit);
        }

        $rows = [];
        $totals = [];
        foreach ($q->get([
            'd.id', 'c1.designation_xml as fr', 'c2.designation_xml as too',
            'd.course_value', 'd.profit', 'd.parser_source_name', 'd.id_crypto_parser', 'd.updated_at',
        ]) as $d) {
            $decision = $this->decide($d, $guard, $expectation, $quarantine, $baseline, $catalog);
            $totals[$decision['decision']] = ($totals[$decision['decision']] ?? 0) + 1;
            $rows[] = $decision;
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'dry_run' => true,
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
     * @param object $d
     * @return array<string,mixed>
     */
    private function decide(
        object $d,
        RateSanityGuard $guard,
        RateConfiguredExpectation $expectation,
        RateExportQuarantine $quarantine,
        IndependentMarketBaseline $baseline,
        BestChangeCurrencyCatalogGuard $catalog,
    ): array {
        $id = (int) $d->id;
        $from = (string) $d->fr;
        $to = (string) $d->too;
        $course = (string) ($d->course_value ?? '0');
        $profit = (string) ($d->profit ?? '0');

        $base = [
            'id' => $id,
            'from' => $from,
            'to' => $to,
            'course_value' => $course,
            'profit' => $profit,
            'source' => (string) ($d->parser_source_name ?? ''),
        ];

        if ($guard->normalize($course) === null) {
            return $base + ['decision' => 'KEEP_QUARANTINED_INVALID', 'reason' => 'non_positive_rate'];
        }

        // Known unsafe local identities without a verified live BC currency.
        if (in_array(strtoupper($to), ['PRUSD', 'PREUR', 'PRRUB'], true)
            || in_array(strtoupper($from), ['PRUSD', 'PREUR', 'PRRUB'], true)) {
            return $base + [
                'decision' => 'KEEP_QUARANTINED_MAPPING',
                'reason' => 'payeer_currency_not_verified_in_live_bestchange_catalog',
            ];
        }

        // Prefer BC row whose out-code matches destination
        $bc = DB::table('bestchange_directions')
            ->where('id_direction_exchange', $id)
            ->where('status', 1)
            ->orderByDesc('updated_at')
            ->get();

        $matchedBc = null;
        foreach ($bc as $row) {
            if (is_string($row->name) && preg_match('/->\s*\[([A-Za-z0-9]+)\]/', $row->name, $m) === 1) {
                if (strtoupper($m[1]) === strtoupper($to)) {
                    $matchedBc = $row;
                    break;
                }
            }
        }

        if ($matchedBc === null && $bc->isNotEmpty()) {
            // Active BC rows exist but none match destination identity
            $any = $bc->first();
            $bcOut = null;
            if (is_string($any->name) && preg_match('/->\s*\[([A-Za-z0-9]+)\]/', $any->name, $m) === 1) {
                $bcOut = strtoupper($m[1]);
            }
            if ($bcOut !== null && $bcOut !== strtoupper($to)) {
                return $base + [
                    'decision' => 'KEEP_QUARANTINED_MAPPING',
                    'reason' => 'bestchange_out_differs_from_destination',
                    'bc_out' => $bcOut,
                ];
            }
        }

        if ($matchedBc !== null) {
            $map = $catalog->validateId((int) $matchedBc->id_currency_out, strtoupper($to));
            if (($map['ok'] ?? false) === false) {
                return $base + [
                    'decision' => 'KEEP_QUARANTINED_MAPPING',
                    'reason' => $map['reason'] ?? 'catalog_mismatch',
                    'mapping' => $map,
                ];
            }

            $bcRate = $guard->normalize((string) $matchedBc->rate_value);
            if ($bcRate === null) {
                return $base + ['decision' => 'KEEP_QUARANTINED_STALE', 'reason' => 'bc_rate_invalid'];
            }

            $analysis = $expectation->analyze($bcRate, $course, profitPercent: $profit);
            $unexplained = $analysis['unexplained_deviation'];
            if ($unexplained !== null && abs($unexplained) > 12.0) {
                return $base + [
                    'decision' => 'KEEP_QUARANTINED_OUTLIER',
                    'reason' => 'unexplained_extreme',
                    'unexplained_deviation' => $unexplained,
                ];
            }
            if ($unexplained !== null && abs($unexplained) > 7.0) {
                return $base + [
                    'decision' => 'KEEP_QUARANTINED_OUTLIER',
                    'reason' => 'unexplained_critical',
                    'unexplained_deviation' => $unexplained,
                ];
            }

            // BC timestamps freeze while the direction is quarantined; do not
            // treat that alone as a permanent hard-stop when the rate formula
            // still matches the matched peer within configured spread.
            $bcAge = $matchedBc->updated_at ? (time() - strtotime((string) $matchedBc->updated_at)) : PHP_INT_MAX;

            $q = $quarantine->evaluate($course, [
                'baseline' => $bcRate,
                'profit_percent' => $profit,
            ]);
            if (!$q['allowed']) {
                return $base + [
                    'decision' => 'KEEP_QUARANTINED_OUTLIER',
                    'reason' => $q['reason'],
                    'export_status' => $q['status'],
                ];
            }

            if ($unexplained !== null && abs($unexplained) <= 1.0) {
                return $base + [
                    'decision' => 'READY_TO_ENABLE',
                    'reason' => 'matched_bc_mapping_and_configured_spread',
                    'unexplained_deviation' => $unexplained,
                    'bc_id' => $matchedBc->id,
                    'bc_age_seconds' => $bcAge,
                    'note' => $bcAge > 7200 ? 'bc_timestamp_stale_while_quarantined_but_formula_ok' : null,
                ];
            }

            if ($bcAge > 7200) {
                return $base + [
                    'decision' => 'KEEP_QUARANTINED_STALE',
                    'reason' => 'bc_updated_stale_and_spread_not_tight',
                    'bc_age_seconds' => $bcAge,
                    'unexplained_deviation' => $unexplained,
                ];
            }

            return $base + [
                'decision' => 'READY_TO_ENABLE',
                'reason' => 'matched_bc_mapping_and_configured_spread',
                'unexplained_deviation' => $unexplained,
                'bc_id' => $matchedBc->id,
                'bc_age_seconds' => $bcAge,
            ];
        }

        // No usable BC — require independent baseline
        $ind = null;
        if (str_contains(strtoupper($to), 'GEL')) {
            foreach (['BTC', 'ETH', 'BNB', 'TRX', 'TON'] as $asset) {
                if (str_starts_with(strtoupper($from), $asset)) {
                    $ind = $baseline->cryptoGel($asset);
                    break;
                }
            }
        }
        if ($ind === null && (str_starts_with(strtoupper($from), 'USDT') || str_starts_with(strtoupper($from), 'USDC'))) {
            if (str_contains(strtoupper($to), 'USD') || in_array(strtoupper($to), ['PRUSD', 'ZELLEUSD'], true)) {
                $ind = ['rate' => '1', 'source' => 'stablecoin_usd_parity', 'age_seconds' => 0];
            }
        }

        if ($ind === null) {
            return $base + [
                'decision' => 'KEEP_QUARANTINED_NO_BASELINE',
                'reason' => 'no_matched_bc_and_no_independent_baseline',
            ];
        }

        $analysis = $expectation->analyze($ind['rate'], $course, profitPercent: $profit);
        $unexplained = $analysis['unexplained_deviation'];
        if ($unexplained === null || abs($unexplained) > 3.0) {
            return $base + [
                'decision' => 'OPERATOR_REVIEW_REQUIRED',
                'reason' => 'independent_baseline_not_close_enough',
                'unexplained_deviation' => $unexplained,
            ];
        }

        return $base + [
            'decision' => 'READY_TO_ENABLE',
            'reason' => 'independent_baseline_ok',
            'unexplained_deviation' => $unexplained,
            'baseline_source' => $ind['source'] ?? null,
        ];
    }
}
