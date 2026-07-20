<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Currency;
use App\Services\Rates\IndependentMarketBaseline;
use App\Services\Rates\RateDirectionEligibility;
use App\Services\Rates\RubFamilyPremiumPolicy;
use App\Services\Reserves\ReserveLinkResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Explain why a direction is active / quarantined / not exportable.
 */
final class RatesDirectionStatusCommand extends Command
{
    protected $signature = 'rates:direction-status
        {id : direction_exchange id}
        {--format=json : json|table}';

    protected $description = 'Explain direction eligibility for quote, order, and export (read-only).';

    public function handle(): int
    {
        $id = (int) $this->argument('id');
        $row = DB::table('direction_exchange as d')
            ->join('currencies as c1', 'c1.id', '=', 'd.id_currency1')
            ->join('currencies as c2', 'c2.id', '=', 'd.id_currency2')
            ->where('d.id', $id)
            ->first([
                'd.id',
                'd.status',
                'd.allow_export',
                'd.course_value',
                'd.profit',
                'd.deleted_at',
                'd.parser_source_name',
                'd.direction_reserve',
                'd.reserve_max_limit',
                'd.type_reserve',
                'd.id_currency1',
                'd.id_currency2',
                'c1.designation_xml as from',
                'c2.designation_xml as to',
                'c1.max_display_reserve as from_reserve',
                'c2.max_display_reserve as to_reserve',
            ]);

        if ($row === null) {
            $this->error('direction_not_found');

            return self::FAILURE;
        }

        $resolved = $this->resolveCanonicalReserve($row);
        $reserveRaw = $resolved['value'];
        $reserveOk = $reserveRaw !== null && is_numeric($reserveRaw) && (float) $reserveRaw > 0;

        $from = (string) $row->from;
        $to = (string) $row->to;
        $baselineInfo = $this->resolveBaseline($from, $to);
        $isCryptoRub = str_contains(strtoupper($to), 'RUB')
            && IndependentMarketBaseline::assetFromCode($from) !== null;
        $policy = RubFamilyPremiumPolicy::fromStorageApp();

        $payload = RateDirectionEligibility::make()->explain([
            'id' => (int) $row->id,
            'status' => (int) $row->status,
            'allow_export' => (int) $row->allow_export,
            'course_value' => (string) $row->course_value,
            'profit' => (string) ($row->profit ?? '0'),
            'deleted_at' => $row->deleted_at,
            'from' => $from,
            'to' => $to,
            'provider_status' => (string) ($row->parser_source_name ?? ''),
            'reserve_ok' => $reserveOk,
            'require_verified_export_mapping' => true,
            'baseline' => $baselineInfo['rate'] ?? null,
            // Public crypto→RUB surfaces must not pass through without an independent baseline.
            'require_independent_baseline' => $isCryptoRub,
        ]);
        $payload['reserve_value'] = $reserveRaw;
        $payload['reserve_source'] = $resolved['source'];
        $payload['baseline_path'] = $baselineInfo['path'] ?? null;
        $payload['baseline_source'] = $baselineInfo['source'] ?? null;
        $payload['baseline_rate'] = $baselineInfo['rate'] ?? null;
        $payload['baseline_age_seconds'] = $baselineInfo['age_seconds'] ?? null;
        $payload['baseline_status'] = ($baselineInfo['rate'] ?? null) === null ? 'NO_BASELINE' : 'OK';
        $payload['rub_family_policy'] = $policy->summary();
        $payload['rub_family'] = $policy->familyForDestination($to)['family_key'] ?? null;
        $payload['economic_note'] = $isCryptoRub && ($baselineInfo['rate'] ?? null) === null
            ? 'crypto_rub_requires_independent_baseline'
            : null;

        if ((string) $this->option('format') === 'table') {
            $this->table(
                ['field', 'value'],
                collect($payload)->map(fn ($v, $k) => [$k, is_scalar($v) || $v === null ? (string) $v : json_encode($v)])->values()->all()
            );
        } else {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }

    /**
     * Match export / order reserve semantics:
     * type_reserve=0 → effective destination currency reserve
     * type_reserve=1 → direction_reserve
     * plus optional reserve_max_limit as capacity ceiling metadata.
     *
     * @return array{value:?string,source:string}
     */
    private function resolveCanonicalReserve(object $row): array
    {
        $type = (int) ($row->type_reserve ?? 0);

        if ($type === 1) {
            $raw = $row->direction_reserve;
            if ($raw !== null && $raw !== '' && is_numeric((string) $raw) && (float) $raw > 0) {
                return ['value' => (string) $raw, 'source' => 'direction_reserve'];
            }

            return ['value' => null, 'source' => 'direction_reserve_missing'];
        }

        try {
            $currency = Currency::query()->with('reserve')->find((int) $row->id_currency2);
            if ($currency && $currency->reserve) {
                $effective = app(ReserveLinkResolver::class)->getEffectiveSumma($currency->reserve, 18);
                if ($effective !== '' && is_numeric($effective) && (float) $effective > 0) {
                    return ['value' => $effective, 'source' => 'currency2_effective_reserve'];
                }
            }
        } catch (Throwable) {
            // fall through
        }

        foreach ([
            ['reserve_max_limit', $row->reserve_max_limit ?? null],
            ['direction_reserve', $row->direction_reserve],
            ['to_max_display_reserve', $row->to_reserve],
            ['from_max_display_reserve', $row->from_reserve],
        ] as [$source, $candidate]) {
            if ($candidate !== null && $candidate !== '' && is_numeric((string) $candidate) && (float) $candidate > 0) {
                return ['value' => (string) $candidate, 'source' => $source];
            }
        }

        return ['value' => null, 'source' => 'none'];
    }

    /**
     * @return array{rate:?string,source:?string,path:?string,age_seconds:?int}
     */
    private function resolveBaseline(string $from, string $to): array
    {
        $empty = ['rate' => null, 'source' => null, 'path' => null, 'age_seconds' => null];
        try {
            $baseline = new IndependentMarketBaseline();
            $fromAsset = IndependentMarketBaseline::assetFromCode($from);
            $toAsset = IndependentMarketBaseline::assetFromCode($to);
            $toU = strtoupper($to);

            if (str_contains($toU, 'RUB') && $fromAsset !== null) {
                if ($fromAsset === 'USDT' || $fromAsset === 'USDC') {
                    $q = $baseline->quote('USDRUB');
                } else {
                    $q = $baseline->cryptoRub($fromAsset);
                }
                if ($q === null) {
                    return $empty;
                }

                return [
                    'rate' => $q['rate'],
                    'source' => $q['source'],
                    'path' => ($fromAsset === 'USDT' || $fromAsset === 'USDC') ? 'stable_to_rub' : 'crypto_to_rub',
                    'age_seconds' => $q['age_seconds'] ?? null,
                ];
            }

            if ($fromAsset !== null && $toAsset !== null) {
                $q = $baseline->cryptoViaUsdt($fromAsset, $toAsset);
                if ($q === null) {
                    return $empty;
                }

                return [
                    'rate' => $q['rate'],
                    'source' => $q['source'],
                    'path' => $q['path'] ?? 'crypto_via_usdt',
                    'age_seconds' => $q['age_seconds'] ?? null,
                ];
            }
        } catch (Throwable) {
            return $empty;
        }

        return $empty;
    }
}
