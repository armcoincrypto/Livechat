<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rates\BestChangeCurrencyCatalogGuard;
use App\Services\Rates\BestChangeMappingVerifier;
use App\Services\Rates\IndependentMarketBaseline;
use App\Services\Rates\RateConfiguredExpectation;
use App\Services\Rates\RateSanityGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Full quarantine triage matrix with technical + business classification.
 */
final class RatesQuarantineTriageCommand extends Command
{
    protected $signature = 'rates:quarantine-triage
        {--format=json : json|table}
        {--write= : Optional path to write inventory JSON}';

    protected $description = 'Classify all quarantined directions into actionable reason groups (read-only).';

    public function handle(
        RateSanityGuard $guard,
        RateConfiguredExpectation $expectation,
        IndependentMarketBaseline $baseline,
    ): int {
        $catalog = BestChangeCurrencyCatalogGuard::fromStorageApp();
        $verifier = BestChangeMappingVerifier::fromStorageApp();

        $focusCodes = [
            'PRUSD', 'CARDVND', 'TON', 'GRAM', 'PREUR', 'PRRUB',
            'BTC', 'ETH', 'BNB', 'USDTTRC20', 'CARDGEL', 'CARDCNY',
        ];
        $mappings = $verifier->verifyLocalCodes($focusCodes);
        $mappingByCode = [];
        foreach ($mappings as $m) {
            $mappingByCode[(string) $m['local_code']] = $m;
        }

        $mapPath = storage_path('app/bestchange_mapping_verification.json');
        file_put_contents($mapPath, json_encode([
            'generated_at' => now()->toIso8601String(),
            'mappings' => $mappings,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $rows = DB::table('direction_exchange as d')
            ->join('currencies as c1', 'c1.id', '=', 'd.id_currency1')
            ->join('currencies as c2', 'c2.id', '=', 'd.id_currency2')
            ->where('d.status', 0)
            ->where('d.allow_export', 2)
            ->whereNull('d.deleted_at')
            ->orderBy('d.id')
            ->get([
                'd.id', 'd.course_value', 'd.profit', 'd.parser_source_name', 'd.updated_at',
                'd.direction_reserve', 'd.type_reserve', 'd.min_price1', 'd.max_price1',
                'c1.id as from_id', 'c1.designation_xml as fr', 'c1.tech_name as from_name',
                'c1.max_display_reserve as from_reserve_cap',
                'c2.id as to_id', 'c2.designation_xml as too', 'c2.tech_name as to_name',
                'c2.max_display_reserve as to_reserve_cap',
            ]);

        $directions = [];
        $byReason = [];
        $byBusiness = [];
        $byFamily = [];

        foreach ($rows as $d) {
            $classified = $this->classifyOne($d, $guard, $expectation, $baseline, $catalog, $mappingByCode);
            $directions[] = $classified;
            $byReason[$classified['primary_reason']] = ($byReason[$classified['primary_reason']] ?? 0) + 1;
            $byBusiness[$classified['business_class']] = ($byBusiness[$classified['business_class']] ?? 0) + 1;
            $fam = $classified['payment_system_family'];
            $byFamily[$fam] = ($byFamily[$fam] ?? 0) + 1;
        }

        ksort($byReason);
        ksort($byBusiness);
        ksort($byFamily);

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'total' => count($directions),
            'by_primary_reason' => $byReason,
            'by_business_class' => $byBusiness,
            'by_payment_system_family' => $byFamily,
            'mapping_verification_path' => $mapPath,
            'mapping_status_totals' => array_count_values(array_map(
                static fn ($m) => (string) $m['status'],
                $mappings
            )),
            'directions' => $directions,
        ];

        $write = (string) ($this->option('write') ?? '');
        if ($write !== '') {
            file_put_contents($write, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        if ((string) $this->option('format') === 'table') {
            $this->table(['reason', 'count'], collect($byReason)->map(fn ($v, $k) => [$k, $v])->values()->all());
            $this->table(['business', 'count'], collect($byBusiness)->map(fn ($v, $k) => [$k, $v])->values()->all());
        } else {
            // Avoid dumping every direction to stdout by default — write path preferred.
            $summary = $payload;
            unset($summary['directions']);
            $summary['directions_omitted'] = true;
            $summary['directions_count'] = count($directions);
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }

    /**
     * @param object $d
     * @param array<string,array<string,mixed>> $mappingByCode
     * @return array<string,mixed>
     */
    private function classifyOne(
        object $d,
        RateSanityGuard $guard,
        RateConfiguredExpectation $expectation,
        IndependentMarketBaseline $baseline,
        BestChangeCurrencyCatalogGuard $catalog,
        array $mappingByCode,
    ): array {
        $id = (int) $d->id;
        $from = strtoupper((string) $d->fr);
        $to = strtoupper((string) $d->too);
        $course = (string) ($d->course_value ?? '0');
        $profit = (string) ($d->profit ?? '0');
        $norm = $guard->normalize($course);
        $rateKind = $this->rateKind($course, $norm);

        $reserve = $this->resolveReserve($d);
        $family = $this->paymentFamily($from, $to);

        $bc = DB::table('bestchange_directions')
            ->where('id_direction_exchange', $id)
            ->where('status', 1)
            ->orderByDesc('updated_at')
            ->get();

        $matchedBc = null;
        $bcOut = null;
        foreach ($bc as $row) {
            if (is_string($row->name) && preg_match('/->\s*\[([A-Za-z0-9]+)\]/', $row->name, $m) === 1) {
                $out = strtoupper($m[1]);
                if ($out === $to) {
                    $matchedBc = $row;
                    $bcOut = $out;
                    break;
                }
                $bcOut ??= $out;
            }
        }

        $sourceAge = null;
        if ($matchedBc?->updated_at) {
            $sourceAge = time() - strtotime((string) $matchedBc->updated_at);
        } elseif (!empty($d->updated_at)) {
            $sourceAge = time() - strtotime((string) $d->updated_at);
        }

        $expected = null;
        $actual = $norm;
        $unexplained = null;
        $baselineQuote = null;
        if ($matchedBc !== null) {
            $bcRate = $guard->normalize((string) $matchedBc->rate_value);
            if ($bcRate !== null && $norm !== null) {
                $analysis = $expectation->analyze($bcRate, $course, profitPercent: $profit);
                $expected = $analysis['expected_customer_rate'] ?? null;
                $unexplained = $analysis['unexplained_deviation'] ?? null;
                $baselineQuote = $bcRate;
            }
        }

        $primary = $this->primaryReason(
            $from,
            $to,
            $rateKind,
            $matchedBc,
            $bcOut,
            $bc,
            $catalog,
            $mappingByCode,
            $unexplained,
            $sourceAge,
            $baseline,
            $reserve,
        );

        $business = $this->businessClass($primary, $reserve, $from, $to);

        return [
            'id' => $id,
            'from' => $from,
            'to' => $to,
            'from_name' => (string) $d->from_name,
            'to_name' => (string) $d->to_name,
            'rate_source' => (string) ($d->parser_source_name ?? ''),
            'source_age_seconds' => $sourceAge,
            'baseline' => $baselineQuote,
            'expected_configured_rate' => $expected,
            'actual_rate' => $actual,
            'unexplained_deviation' => $unexplained,
            'reserve' => $reserve,
            'recent_order_count' => null,
            'last_order_timestamp' => null,
            'bestchange_export_mapping' => $matchedBc ? [
                'bc_row_id' => (int) $matchedBc->id,
                'name' => (string) $matchedBc->name,
                'id_currency_out' => (int) $matchedBc->id_currency_out,
                'rate_value' => (string) $matchedBc->rate_value,
            ] : null,
            'website_visibility' => 'inactive',
            'primary_reason' => $primary,
            'business_class' => $business,
            'payment_system_family' => $family,
            'restore_decision' => $business === 'READY_FOR_CONTROLLED_RESTORE' ? 'READY_TO_RESTORE' : 'KEEP_' . $primary,
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $mappingByCode
     */
    private function rateKind(string $course, ?string $norm): string
    {
        $raw = trim($course);
        if ($raw === '' || strcasecmp($raw, 'null') === 0) {
            return 'MISSING_RATE';
        }
        if (!is_numeric($raw)) {
            return 'MISSING_RATE';
        }
        if ((float) $raw < 0) {
            return 'NEGATIVE_RATE';
        }
        if ($norm === null || (float) $raw === 0.0) {
            return 'ZERO_RATE';
        }

        return 'OK';
    }

    private function primaryReason(
        string $from,
        string $to,
        string $rateKind,
        mixed $matchedBc,
        ?string $bcOut,
        mixed $bc,
        BestChangeCurrencyCatalogGuard $catalog,
        array $mappingByCode,
        mixed $unexplained,
        ?int $sourceAge,
        IndependentMarketBaseline $baseline,
        array $reserve,
    ): string {
        if ($rateKind !== 'OK') {
            return $rateKind;
        }

        foreach ([$from, $to] as $code) {
            $map = $mappingByCode[$code] ?? null;
            if (is_array($map) && in_array($map['status'] ?? '', ['DRIFTED', 'AMBIGUOUS', 'DEPRECATED'], true)) {
                if (in_array($code, [$from, $to], true) && ($map['status'] ?? '') === 'DRIFTED') {
                    return $code === $from ? 'SOURCE_MAPPING_MISMATCH' : 'DESTINATION_MAPPING_MISMATCH';
                }
                if (($map['status'] ?? '') === 'AMBIGUOUS') {
                    return 'OPERATOR_MAPPING_REQUIRED';
                }
                if (($map['status'] ?? '') === 'DEPRECATED') {
                    return 'DEPRECATED_PAYMENT_METHOD';
                }
            }
        }

        // Hard-coded known drifts
        if (in_array($from, ['PRUSD', 'PREUR', 'PRRUB'], true) || in_array($to, ['PRUSD', 'PREUR', 'PRRUB'], true)) {
            return 'DESTINATION_MAPPING_MISMATCH';
        }
        if ($from === 'TON' || $to === 'TON' || $from === 'GRAM' || $to === 'GRAM') {
            $tonMap = $mappingByCode['TON'] ?? null;
            $gramMap = $mappingByCode['GRAM'] ?? null;
            if (($gramMap['status'] ?? '') !== 'VERIFIED' && ($to === 'GRAM' || $from === 'GRAM')) {
                return 'BESTCHANGE_CODE_DRIFT';
            }
            $cov = $baseline->coverage();
            $gaps = $cov['gaps'] ?? [];
            foreach ($gaps as $gap) {
                if (is_string($gap) && str_contains(strtoupper($gap), 'TON')) {
                    return 'NO_BASELINE';
                }
            }
            if ($from === 'TON' || $to === 'TON') {
                return 'NO_BASELINE';
            }
        }

        if ($matchedBc !== null) {
            $map = $catalog->validateId((int) $matchedBc->id_currency_out, $to);
            if (($map['ok'] ?? false) === false) {
                return 'BESTCHANGE_CODE_DRIFT';
            }
        } elseif ($bc->isNotEmpty() && $bcOut !== null && $bcOut !== $to) {
            return 'DESTINATION_MAPPING_MISMATCH';
        }

        if ($sourceAge !== null && $sourceAge > 86400 * 7) {
            return 'STALE_SOURCE';
        }

        if ($unexplained !== null && abs((float) $unexplained) > 12) {
            return 'PEER_OUTLIER';
        }

        if (($reserve['value'] ?? null) === null || (float) ($reserve['value'] ?? 0) <= 0) {
            return 'NO_RESERVE';
        }

        if ($matchedBc === null) {
            return 'NO_BASELINE';
        }

        // Technically looking ok but still quarantined — treat as business-disabled unless ready
        if ($unexplained !== null && abs((float) $unexplained) <= 1.0 && (float) $reserve['value'] > 0) {
            return 'VALID_READY_TO_RESTORE';
        }

        return 'INACTIVE_BUSINESS_DIRECTION';
    }

    private function businessClass(string $primary, array $reserve, string $from, string $to): string
    {
        if ($primary === 'VALID_READY_TO_RESTORE') {
            return 'READY_FOR_CONTROLLED_RESTORE';
        }
        if (in_array($primary, [
            'ZERO_RATE', 'NEGATIVE_RATE', 'MISSING_RATE', 'STALE_SOURCE', 'NO_BASELINE',
            'BESTCHANGE_CODE_DRIFT', 'DESTINATION_MAPPING_MISMATCH', 'SOURCE_MAPPING_MISMATCH',
            'DIRECT_RECIPROCAL_INVERSION', 'PEER_OUTLIER', 'OPERATOR_MAPPING_REQUIRED',
        ], true)) {
            return 'TECHNICALLY_BROKEN';
        }
        if ($primary === 'NO_RESERVE') {
            return 'TECHNICALLY_VALID_NO_RESERVE';
        }
        if ($primary === 'DEPRECATED_PAYMENT_METHOD') {
            return 'DEPRECATED';
        }
        if ($primary === 'INACTIVE_BUSINESS_DIRECTION') {
            return 'TECHNICALLY_VALID_BUSINESS_DISABLED';
        }

        return 'OPERATOR_DECISION_REQUIRED';
    }

    /**
     * @return array{value: string|null, source: string}
     */
    private function resolveReserve(object $d): array
    {
        if ((int) ($d->type_reserve ?? 0) === 1) {
            $v = (string) ($d->direction_reserve ?? '0');

            return ['value' => $v, 'source' => 'direction_reserve'];
        }
        $cap = $d->to_reserve_cap ?? null;

        return [
            'value' => $cap !== null ? (string) $cap : null,
            'source' => 'currency_max_display_reserve',
        ];
    }

    private function paymentFamily(string $from, string $to): string
    {
        $blob = $from . '_' . $to;
        foreach ([
            'CARD' => 'card',
            'WIRE' => 'wire',
            'PAYEER' => 'payeer',
            'PRUSD' => 'payeer',
            'PREUR' => 'payeer',
            'PRRUB' => 'payeer',
            'ZELLE' => 'zelle',
            'SBP' => 'sbp',
            'TON' => 'ton',
            'GRAM' => 'gram',
            'USDT' => 'stablecoin',
            'USDC' => 'stablecoin',
            'BTC' => 'btc',
            'ETH' => 'eth',
            'BNB' => 'bnb',
        ] as $needle => $fam) {
            if (str_contains($blob, $needle)) {
                return $fam;
            }
        }

        return 'other';
    }
}
