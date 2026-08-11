<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Models\Currency;
use App\Models\DirectionExchange;
use App\Services\Reserves\ReserveLinkResolver;
use Throwable;

/**
 * Single eligibility evaluator for quote / order / export surfaces.
 *
 * Fail-closed: missing evidence → not eligible.
 * Crypto→RUB public surfaces must also pass RubFamilyPremiumPolicy::evaluateCoinRub.
 */
final class RateDirectionEligibility
{
    public const ERROR_DIRECTION_TEMPORARILY_UNAVAILABLE = 'DIRECTION_TEMPORARILY_UNAVAILABLE';

    public function __construct(
        private readonly RateExportQuarantine $quarantine,
        private readonly BestChangeMappingVerifier $mappingVerifier,
        private readonly ?RubFamilyPremiumPolicy $rubPolicy = null,
        private readonly ?IndependentMarketBaseline $baseline = null,
        private readonly ?RateConfiguredExpectation $expectation = null,
    ) {
    }

    public static function make(): self
    {
        return new self(
            quarantine: new RateExportQuarantine(),
            mappingVerifier: BestChangeMappingVerifier::fromStorageApp(),
            rubPolicy: RubFamilyPremiumPolicy::fromStorageApp(),
            baseline: new IndependentMarketBaseline(),
            expectation: new RateConfiguredExpectation(),
        );
    }

    /**
     * Canonical public-surface evaluation for a loaded DirectionExchange.
     *
     * @return array{
     *   direction_id:int|null,
     *   from:string,
     *   to:string,
     *   quote_allowed:bool,
     *   order_allowed:bool,
     *   export_allowed:bool,
     *   BestChange_allowed:bool,
     *   eligible_for_quote:bool,
     *   eligible_for_order:bool,
     *   eligible_for_export:bool,
     *   classification:string|null,
     *   baseline_status:string,
     *   policy_status:string,
     *   reserve_status:string,
     *   mapping_status:array<string,string>,
     *   parity_status:string,
     *   blocking_reasons:list<string>,
     *   reasons:list<string>,
     *   error_code:string|null,
     *   course_value:string,
     *   baseline_rate:?string,
     *   raw_market_deviation:float|null,
     *   unexplained_vs_expected_percent:float|null,
     *   active:bool,
     *   quarantined:bool,
     *   deprecated:bool,
     *   status:int,
     *   allow_export:int,
     *   rate_quarantine:array<string,mixed>
     * }
     */
    public function evaluateDirection(DirectionExchange $direction): array
    {
        // Load full currency rows. Constraining columns to id,designation_xml
        // strips id_payment and poisons $currency->payment as a null relation,
        // which then 500s CurrencyResource during /rates/operations serialization.
        $direction->loadMissing(['currency1', 'currency2']);

        // Release B1: proven public duplicates are not quoteable/orderable/exported.
        if (PublicDuplicateExclusion::isExcluded((int) ($direction->id ?? 0))) {
            $from = strtoupper((string) ($direction->currency1?->designation_xml ?? ''));
            $to = strtoupper((string) ($direction->currency2?->designation_xml ?? ''));
            return [
                'direction_id' => (int) $direction->id,
                'from' => $from,
                'to' => $to,
                'quote_allowed' => false,
                'order_allowed' => false,
                'export_allowed' => false,
                'BestChange_allowed' => false,
                'eligible_for_quote' => false,
                'eligible_for_order' => false,
                'eligible_for_export' => false,
                'classification' => 'PUBLIC_DUPLICATE_EXCLUDED',
                'baseline_status' => 'not_applicable',
                'policy_status' => 'not_applicable',
                'reserve_status' => 'not_applicable',
                'mapping_status' => [],
                'parity_status' => 'not_applicable',
                'blocking_reasons' => ['public_duplicate_excluded_b1'],
                'reasons' => ['public_duplicate_excluded_b1'],
                'error_code' => self::ERROR_DIRECTION_TEMPORARILY_UNAVAILABLE,
                'course_value' => (string) ($direction->course_value ?? ''),
                'baseline_rate' => null,
                'raw_market_deviation' => null,
                'unexplained_vs_expected_percent' => null,
                'active' => false,
                'quarantined' => false,
                'deprecated' => false,
                'status' => (int) ($direction->status ?? 0),
                'allow_export' => (int) ($direction->allow_export ?? 0),
                'rate_quarantine' => ['ok' => false, 'reason' => 'public_duplicate_excluded_b1'],
                'provider_status' => (string) ($direction->parser_source_name ?? ''),
            ];
        }

        // C3-B: owner-retired currencies (TUSDTRC20, DAI) — no new public operation.
        if (CurrencyPublicRetirement::directionTouchesRetired(
            (int) ($direction->id_currency1 ?? 0),
            (int) ($direction->id_currency2 ?? 0),
            (string) ($direction->currency1?->designation_xml ?? ''),
            (string) ($direction->currency2?->designation_xml ?? ''),
        )) {
            $from = strtoupper((string) ($direction->currency1?->designation_xml ?? ''));
            $to = strtoupper((string) ($direction->currency2?->designation_xml ?? ''));
            return [
                'direction_id' => (int) $direction->id,
                'from' => $from,
                'to' => $to,
                'quote_allowed' => false,
                'order_allowed' => false,
                'export_allowed' => false,
                'BestChange_allowed' => false,
                'eligible_for_quote' => false,
                'eligible_for_order' => false,
                'eligible_for_export' => false,
                'classification' => 'CURRENCY_PUBLICLY_RETIRED',
                'baseline_status' => 'not_applicable',
                'policy_status' => 'not_applicable',
                'reserve_status' => 'not_applicable',
                'mapping_status' => [],
                'parity_status' => 'not_applicable',
                'blocking_reasons' => ['currency_publicly_retired_c3b'],
                'reasons' => ['currency_publicly_retired_c3b'],
                'error_code' => self::ERROR_DIRECTION_TEMPORARILY_UNAVAILABLE,
                'course_value' => (string) ($direction->course_value ?? ''),
                'baseline_rate' => null,
                'raw_market_deviation' => null,
                'unexplained_vs_expected_percent' => null,
                'active' => false,
                'quarantined' => false,
                'deprecated' => true,
                'status' => (int) ($direction->status ?? 0),
                'allow_export' => (int) ($direction->allow_export ?? 0),
                'rate_quarantine' => ['ok' => false, 'reason' => 'currency_publicly_retired_c3b'],
                'provider_status' => (string) ($direction->parser_source_name ?? ''),
            ];
        }

        // ZELLEUSD→dest: require exact valid USDTTRC20→dest benchmark (fail closed).
        $zelleResolver = ZelleUsdBenchmarkResolver::make();
        if ($zelleResolver->isZelleOutgoing($direction)) {
            $bench = $zelleResolver->resolve($direction);
            if (!$bench->eligible) {
                $from = strtoupper((string) ($direction->currency1?->designation_xml ?? ''));
                $to = strtoupper((string) ($direction->currency2?->designation_xml ?? ''));

                return [
                    'direction_id' => (int) $direction->id,
                    'from' => $from,
                    'to' => $to,
                    'quote_allowed' => false,
                    'order_allowed' => false,
                    'export_allowed' => false,
                    'BestChange_allowed' => false,
                    'eligible_for_quote' => false,
                    'eligible_for_order' => false,
                    'eligible_for_export' => false,
                    'classification' => $bench->reasonCode,
                    'baseline_status' => 'zelle_usdt_benchmark_required',
                    'policy_status' => 'not_applicable',
                    'reserve_status' => 'not_applicable',
                    'mapping_status' => [],
                    'parity_status' => 'not_applicable',
                    'blocking_reasons' => [$bench->reasonCode],
                    'reasons' => [$bench->reasonCode],
                    'error_code' => self::ERROR_DIRECTION_TEMPORARILY_UNAVAILABLE,
                    'course_value' => (string) ($direction->course_value ?? ''),
                    'baseline_rate' => $bench->benchmarkRate,
                    'raw_market_deviation' => null,
                    'unexplained_vs_expected_percent' => null,
                    'active' => false,
                    'quarantined' => true,
                    'deprecated' => false,
                    'status' => (int) ($direction->status ?? 0),
                    'allow_export' => (int) ($direction->allow_export ?? 0),
                    'rate_quarantine' => ['ok' => false, 'reason' => $bench->reasonCode],
                    'provider_status' => ZelleUsdUsdtBenchmarkAuthority::PARSER_SOURCE_NAME,
                ];
            }
        }

        $from = strtoupper((string) ($direction->currency1?->designation_xml ?? ''));
        $to = strtoupper((string) ($direction->currency2?->designation_xml ?? ''));
        // Every RUB destination is policy-bound. An unknown source identity is
        // not an exemption: resolveBaseline() will return null and the policy
        // will classify it NO_BASELINE (fail closed on every public surface).
        $isRubDirection = str_contains($to, 'RUB');

        $baselineInfo = $isRubDirection ? $this->resolveBaseline($from, $to) : [
            'rate' => null,
            'source' => null,
            'path' => null,
            'age_seconds' => null,
        ];
        $reserve = $this->resolveReserve($direction);

        $payload = $this->explain([
            'id' => (int) $direction->id,
            'status' => (int) $direction->status,
            'allow_export' => (int) $direction->allow_export,
            'course_value' => (string) ($direction->course_value ?? ''),
            'profit' => (string) ($direction->profit ?? '0'),
            'deleted_at' => $direction->deleted_at,
            // Fail-closed: a missing currency relation (-1) is treated as not visible.
            // See F2 remediation — a hidden/removed currency must never be quote/order eligible
            // even when the direction row itself is status=1.
            'currency1_status' => $direction->currency1 !== null ? (int) $direction->currency1->status : -1,
            'currency2_status' => $direction->currency2 !== null ? (int) $direction->currency2->status : -1,
            'from' => $from,
            'to' => $to,
            'provider_status' => (string) ($direction->parser_source_name ?? ''),
            'reserve_ok' => $reserve['ok'],
            'require_verified_export_mapping' => true,
            'baseline' => $baselineInfo['rate'],
            'require_independent_baseline' => $isRubDirection,
        ]);

        $classification = null;
        $policyStatus = 'not_applicable';
        $raw = null;
        $unexplained = null;
        $reasons = $payload['reasons'];

        if ($isRubDirection) {
            $policy = $this->rubPolicy ?? RubFamilyPremiumPolicy::fromStorageApp();
            $expectation = $this->expectation ?? new RateConfiguredExpectation();
            $baselineRateFloat = $baselineInfo['rate'] !== null && is_numeric((string) $baselineInfo['rate'])
                ? (float) $baselineInfo['rate']
                : null;
            $courseRaw = null;
            $commercialRaw = null;
            if ($baselineInfo['rate'] !== null) {
                $analysis = $expectation->analyze(
                    baseline: (string) $baselineInfo['rate'],
                    actual: (string) ($direction->course_value ?? ''),
                    profitPercent: (string) ($direction->profit ?? '0'),
                );
                $courseRaw = $analysis['raw_market_deviation'] ?? null;

                // Prefer customer-facing commercial floating vs CBR when intentional
                // commercial pricing is configured (or DERIVED ownership applies).
                try {
                    $calc = CanonicalDirectionRateCalculator::make();
                    $floating = $calc->calculate($direction, RateMode::Floating, RateChannel::Website);
                    if (
                        is_numeric($floating->finalRate)
                        && bccomp($floating->finalRate, '0', 8) > 0
                        && $baselineRateFloat !== null
                        && $baselineRateFloat > 0.0
                    ) {
                        $commercialRaw = (((float) $floating->finalRate) / $baselineRateFloat - 1.0) * 100.0;
                    }
                } catch (Throwable) {
                    $commercialRaw = null;
                }
            }

            $useCommercial = $policy->familyUsesIntentionalAbsoluteTarget($to)
                || (string) ($direction->parser_source_name ?? '') === 'DERIVED_MARKET_BASELINE';
            $raw = ($useCommercial && $commercialRaw !== null) ? $commercialRaw : $courseRaw;

            $eval = $policy->evaluateCoinRub(
                $to,
                $raw === null ? null : (float) $raw,
                (float) ($direction->profit ?? 0),
                $baselineRateFloat,
                $from,
            );
            $classification = $eval['classification'];
            $unexplained = $eval['unexplained_vs_expected_percent'];
            $policyStatus = $policy->isApproved() ? 'approved' : 'not_approved';
            foreach ($eval['reasons'] as $r) {
                $reasons[] = $r;
            }

            // Phase-3 public surface mapping (canonical).
            $passClass = in_array($classification, ['PASS', 'PASS_EXPLAINED_SPREAD'], true);
            $reviewClass = $classification === 'REVIEW';
            $blockQuote = in_array($classification, [
                'QUARANTINE_REQUIRED', 'NO_BASELINE', 'NO_POLICY',
            ], true);

            // Rub-family policy owns coin→RUB public surfaces. Generic
            // RateExportQuarantine unexplained bands compare actual to
            // baseline*(1-profit), which inflates "unexplained" for legitimate
            // OTC premiums ABOVE CBR mid and was clearing quotes even for PASS.
            // Defer only unexplained-band quarantine (not invalid/stale/no_baseline)
            // when RubFamily already classified PASS/REVIEW within family ceilings.
            $qReason = (string) ($payload['rate_quarantine']['reason'] ?? '');
            $unexplainedBandOnly = in_array($qReason, [
                'unexplained_critical_deviation',
                'unexplained_extreme_deviation',
            ], true);
            if ($unexplainedBandOnly && ($passClass || $reviewClass) && !$blockQuote) {
                $active = (bool) ($payload['active'] ?? false);
                $quarantined = (bool) ($payload['quarantined'] ?? false);
                $deprecated = (bool) ($payload['deprecated'] ?? false);
                $payload['eligible_for_quote'] = $active && !$quarantined && !$deprecated;
                $payload['eligible_for_order'] = $payload['eligible_for_quote'];
                $payload['eligible_for_export'] = $payload['eligible_for_quote'];
                $reasons = array_values(array_filter(
                    $reasons,
                    static fn (string $r): bool => !str_starts_with($r, 'rate_unexplained_'),
                ));
                $reasons[] = 'rub_family_owns_unexplained_band';
            }

            $payload['eligible_for_quote'] = $payload['eligible_for_quote'] && !$blockQuote;
            $payload['eligible_for_order'] = $payload['eligible_for_order']
                && $passClass
                && (bool) $eval['order_allowed']
                && $reserve['ok'];
            $payload['eligible_for_export'] = $payload['eligible_for_export']
                && $passClass
                && (bool) $eval['export_allowed']
                && $reserve['ok'];

            // ZELLEUSD single-rate authority: BASE is the USDTTRC20 peer / ZELLE
            // hierarchy, not a free-floating CBR premium. Rub-family REVIEW vs CBR
            // must not strip order/export from otherwise AUTO_CANONICAL ZELLE→RUB
            // rows; hard quarantine / NO_BASELINE still fail closed above.
            $zelleAutoCanonical = $from === 'ZELLEUSD'
                && (string) ($direction->parser_source_name ?? '') === ZelleUsdUsdtBenchmarkAuthority::PARSER_SOURCE_NAME;
            // Rub-family sets order_allowed/export_allowed=false on REVIEW itself;
            // for ZELLE AUTO_CANONICAL that flag must not gate the exemption.
            if ($reviewClass && $zelleAutoCanonical && !$blockQuote && $reserve['ok']) {
                $payload['eligible_for_order'] = (bool) $payload['eligible_for_quote'];
                $payload['eligible_for_export'] = (bool) $payload['eligible_for_quote'];
                $reasons[] = 'zelle_authority_owns_rub_review_band';
            } elseif ($reviewClass) {
                $payload['eligible_for_order'] = false;
                $payload['eligible_for_export'] = false;
                $reasons[] = 'rub_family_review_public_blocked';
            }
            if ($blockQuote) {
                $reasons[] = 'rub_family_' . strtolower((string) $classification);
            }
            if (!$reserve['ok']) {
                $payload['eligible_for_order'] = false;
                $payload['eligible_for_export'] = false;
                $reasons[] = 'no_reserve';
            }
        }

        $reasons = $this->uniqueReasons($reasons);
        $quoteAllowed = (bool) $payload['eligible_for_quote'];
        $orderAllowed = (bool) $payload['eligible_for_order'];
        $exportAllowed = (bool) $payload['eligible_for_export'];

        $errorCode = null;
        if (!$quoteAllowed || !$orderAllowed || !$exportAllowed) {
            if (!$quoteAllowed || !$orderAllowed) {
                $errorCode = self::ERROR_DIRECTION_TEMPORARILY_UNAVAILABLE;
            }
        }

        return [
            'direction_id' => $payload['direction_id'],
            'from' => $from,
            'to' => $to,
            'quote_allowed' => $quoteAllowed,
            'order_allowed' => $orderAllowed,
            'export_allowed' => $exportAllowed,
            'BestChange_allowed' => $exportAllowed,
            'eligible_for_quote' => $quoteAllowed,
            'eligible_for_order' => $orderAllowed,
            'eligible_for_export' => $exportAllowed,
            'classification' => $classification,
            'baseline_status' => $baselineInfo['rate'] === null
                ? ($isRubDirection ? 'NO_BASELINE' : 'not_required')
                : 'OK',
            'policy_status' => $policyStatus,
            'reserve_status' => $reserve['ok'] ? 'adequate' : 'inadequate_or_missing',
            'mapping_status' => $payload['mapping_status'],
            'parity_status' => 'not_evaluated',
            'blocking_reasons' => $reasons,
            'reasons' => $reasons,
            'error_code' => $errorCode,
            'course_value' => $payload['course_value'],
            'baseline_rate' => $baselineInfo['rate'],
            'baseline_source' => $baselineInfo['source'],
            'baseline_age_seconds' => $baselineInfo['age_seconds'],
            'raw_market_deviation' => $raw === null ? null : (float) $raw,
            'unexplained_vs_expected_percent' => $unexplained,
            'active' => $payload['active'],
            'quarantined' => $payload['quarantined'],
            'deprecated' => $payload['deprecated'],
            'status' => $payload['status'],
            'allow_export' => $payload['allow_export'],
            'rate_quarantine' => $payload['rate_quarantine'],
            'provider_status' => $payload['provider_status'],
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public function explain(array $row): array
    {
        $status = (int) ($row['status'] ?? -1);
        $allowExport = (int) ($row['allow_export'] ?? -1);
        $course = (string) ($row['course_value'] ?? '');
        $from = strtoupper((string) ($row['from'] ?? ''));
        $to = strtoupper((string) ($row['to'] ?? ''));
        $deleted = !empty($row['deleted_at']);
        $currency1Status = (int) ($row['currency1_status'] ?? -1);
        $currency2Status = (int) ($row['currency2_status'] ?? -1);
        $currencyHidden = $currency1Status !== 0 || $currency2Status !== 0;

        $reasons = [];
        $active = !$deleted && $status === 1 && !$currencyHidden;
        $quarantined = !$deleted && $status === 0 && $allowExport === 2;
        $deprecated = !$deleted && $status === 2;

        if ($deleted) {
            $reasons[] = 'soft_deleted';
        }
        if ($deprecated) {
            $reasons[] = 'status_deprecated_or_removed';
        }
        if ($quarantined) {
            $reasons[] = 'quarantined_status0_allow_export2';
        }
        if ($status !== 1) {
            $reasons[] = 'direction_not_active';
        }
        if ($currencyHidden) {
            $reasons[] = 'currency_hidden_or_removed';
        }
        if ($allowExport === 2) {
            $reasons[] = 'export_hard_disabled';
        }

        $q = $this->quarantine->evaluate($course, [
            'profit_percent' => (string) ($row['profit'] ?? '0'),
            'baseline' => isset($row['baseline']) ? (string) $row['baseline'] : null,
            'force_block_reason' => $row['force_block_reason'] ?? null,
            'allow_no_baseline' => empty($row['require_independent_baseline']),
        ]);
        if (!$q['allowed']) {
            $reasons[] = 'rate_' . ($q['reason'] ?? 'blocked');
        }

        $mappingStatuses = [];
        foreach ([$from, $to] as $code) {
            if ($code === '') {
                continue;
            }
            $m = $this->mappingVerifier->verifyCode($code);
            $mappingStatuses[$code] = $m['status'] ?? 'UNKNOWN';
            $st = strtoupper((string) ($m['status'] ?? ''));
            if ($st !== 'VERIFIED' && in_array($code, ['PRUSD', 'PREUR', 'PRRUB', 'TON', 'BNB'], true)) {
                $reasons[] = 'mapping_' . strtolower($code) . '_' . strtolower($st);
            }
            if ($st === 'DRIFTED' || $st === 'AMBIGUOUS' || $st === 'ABSENT' || $st === 'DEPRECATED') {
                if (!empty($row['require_verified_export_mapping'])) {
                    $reasons[] = 'export_mapping_not_verified_' . $code;
                }
            }
        }

        $reserveOk = !empty($row['reserve_ok']);
        if (array_key_exists('reserve_ok', $row) && !$reserveOk) {
            $reasons[] = 'reserve_inadequate';
        }

        $mappingBlocksExport = false;
        if (!empty($row['require_verified_export_mapping'])) {
            foreach ($mappingStatuses as $st) {
                if (strtoupper((string) $st) !== 'VERIFIED') {
                    $mappingBlocksExport = true;
                    break;
                }
            }
        }

        $eligibleForQuote = $active && $q['allowed'] && !$quarantined && !$deprecated;
        $eligibleForExport = $eligibleForQuote && $allowExport !== 2 && !$mappingBlocksExport;
        $eligibleForOrder = $eligibleForQuote && (array_key_exists('reserve_ok', $row) ? $reserveOk : true);

        return [
            'direction_id' => $row['id'] ?? null,
            'from' => $from,
            'to' => $to,
            'active' => $active,
            'quarantined' => $quarantined,
            'deprecated' => $deprecated,
            'eligible_for_quote' => $eligibleForQuote,
            'eligible_for_order' => $eligibleForOrder,
            'eligible_for_export' => $eligibleForExport,
            'reasons' => $this->uniqueReasons($reasons),
            'mapping_status' => $mappingStatuses,
            'provider_status' => $row['provider_status'] ?? null,
            'reserve_status' => array_key_exists('reserve_ok', $row)
                ? ($reserveOk ? 'adequate' : 'inadequate_or_missing')
                : 'not_evaluated',
            'rate_quarantine' => $q,
            'course_value' => $course,
            'status' => $status,
            'allow_export' => $allowExport,
        ];
    }

    /**
     * @return array{ok:bool,value:?string,source:string}
     */
    private function resolveReserve(DirectionExchange $direction): array
    {
        $type = (int) ($direction->type_reserve ?? 0);
        if ($type === 1) {
            $raw = $direction->direction_reserve;
            if ($raw !== null && $raw !== '' && is_numeric((string) $raw) && (float) $raw > 0) {
                return ['ok' => true, 'value' => (string) $raw, 'source' => 'direction_reserve'];
            }

            return ['ok' => false, 'value' => null, 'source' => 'direction_reserve_missing'];
        }

        try {
            $currency = Currency::query()->with('reserve')->find((int) $direction->id_currency2);
            if ($currency && $currency->reserve) {
                $effective = app(ReserveLinkResolver::class)->getEffectiveSumma($currency->reserve, 18);
                if ($effective !== '' && is_numeric($effective) && (float) $effective > 0) {
                    return ['ok' => true, 'value' => $effective, 'source' => 'currency2_effective_reserve'];
                }
            }
        } catch (Throwable) {
            // fall through
        }

        return ['ok' => false, 'value' => null, 'source' => 'none'];
    }

    /**
     * @return array{rate:?string,source:?string,path:?string,age_seconds:?int}
     */
    private function resolveBaseline(string $from, string $to): array
    {
        $empty = ['rate' => null, 'source' => null, 'path' => null, 'age_seconds' => null];
        try {
            $baseline = $this->baseline ?? new IndependentMarketBaseline();
            $asset = IndependentMarketBaseline::assetFromCode($from);
            if ($asset === null) {
                return $empty;
            }
            if ($asset === 'USDT' || $asset === 'USDC') {
                $q = $baseline->quote('USDRUB');

                return $q ? [
                    'rate' => $q['rate'],
                    'source' => $q['source'],
                    'path' => 'stable_to_rub',
                    'age_seconds' => $q['age_seconds'] ?? null,
                ] : $empty;
            }
            $q = $baseline->cryptoRub($asset);

            return $q ? [
                'rate' => $q['rate'],
                'source' => $q['source'],
                'path' => 'crypto_to_rub',
                'age_seconds' => $q['age_seconds'] ?? null,
            ] : $empty;
        } catch (Throwable) {
            return $empty;
        }
    }

    /**
     * @param list<string> $reasons
     * @return list<string>
     */
    private function uniqueReasons(array $reasons): array
    {
        $seen = [];
        $uniq = [];
        foreach ($reasons as $r) {
            if (isset($seen[$r])) {
                continue;
            }
            $seen[$r] = true;
            $uniq[] = $r;
        }

        return $uniq;
    }
}
