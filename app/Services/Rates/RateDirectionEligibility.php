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
        if (!$direction->relationLoaded('currency1') || !$direction->relationLoaded('currency2')) {
            $direction->loadMissing(['currency1', 'currency2']);
        }
        $from = strtoupper((string) ($direction->currency1?->designation_xml ?? ''));
        $to = strtoupper((string) ($direction->currency2?->designation_xml ?? ''));
        // Every RUB destination is policy-bound. An unknown source identity is
        // not an exemption: resolveBaseline() will return null and the policy
        // will classify it NO_BASELINE (fail closed on every public surface).
        $isRubDirection = str_contains($to, 'RUB');
        $providerStatus = (string) ($direction->parser_source_name ?? '');
        $circularSourceDetected = $isRubDirection
            && str_contains(strtolower($providerStatus), 'bestchange');

        $baselineInfo = $isRubDirection ? $this->resolveBaseline($from, $to) : [
            'rate' => null,
            'source' => null,
            'source_type' => null,
            'provider' => null,
            'symbol' => null,
            'path' => null,
            'age_seconds' => null,
            'circular_source_detected' => false,
        ];
        $reserve = $this->resolveReserve($direction);

        $payload = $this->explain([
            'id' => (int) $direction->id,
            'status' => (int) $direction->status,
            'allow_export' => (int) $direction->allow_export,
            'course_value' => (string) ($direction->course_value ?? ''),
            'profit' => (string) ($direction->profit ?? '0'),
            'deleted_at' => $direction->deleted_at,
            'from' => $from,
            'to' => $to,
            'provider_status' => $providerStatus,
            'reserve_ok' => $reserve['ok'],
            'require_verified_export_mapping' => true,
            'baseline' => $baselineInfo['rate'],
            'require_independent_baseline' => $isRubDirection,
            'force_block_reason' => $circularSourceDetected ? 'circular_source' : null,
        ]);

        $classification = null;
        $policyStatus = 'not_applicable';
        $raw = null;
        $unexplained = null;
        $reasons = $payload['reasons'];

        if ($isRubDirection) {
            $policy = $this->rubPolicy ?? RubFamilyPremiumPolicy::fromStorageApp();
            $expectation = $this->expectation ?? new RateConfiguredExpectation();
            if ($baselineInfo['rate'] !== null) {
                $analysis = $expectation->analyze(
                    baseline: (string) $baselineInfo['rate'],
                    actual: (string) ($direction->course_value ?? ''),
                    profitPercent: (string) ($direction->profit ?? '0'),
                );
                $raw = $analysis['raw_market_deviation'] ?? null;
            }
            $eval = $policy->evaluateCoinRub(
                $to,
                $raw === null ? null : (float) $raw,
                (float) ($direction->profit ?? 0),
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

            $payload['eligible_for_quote'] = $payload['eligible_for_quote'] && !$blockQuote;
            $payload['eligible_for_order'] = $payload['eligible_for_order']
                && $passClass
                && (bool) $eval['order_allowed']
                && $reserve['ok'];
            $payload['eligible_for_export'] = $payload['eligible_for_export']
                && $passClass
                && (bool) $eval['export_allowed']
                && $reserve['ok'];

            if ($reviewClass) {
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
            'reserve_value' => $reserve['value'],
            'reserve_source' => $reserve['source'],
            'mapping_status' => $payload['mapping_status'],
            'parity_status' => 'not_evaluated',
            'blocking_reasons' => $reasons,
            'reasons' => $reasons,
            'error_code' => $errorCode,
            'course_value' => $payload['course_value'],
            'baseline_rate' => $baselineInfo['rate'],
            'baseline_source' => $baselineInfo['source'],
            'baseline_source_type' => $baselineInfo['source_type'],
            'baseline_provider' => $baselineInfo['provider'],
            'baseline_symbol' => $baselineInfo['symbol'],
            'baseline_age_seconds' => $baselineInfo['age_seconds'],
            'circular_source_detected' => $circularSourceDetected
                || !empty($baselineInfo['circular_source_detected']),
            'snapshot_id' => IndependentMarketBaseline::currentSnapshot()['id'] ?? null,
            'snapshot_timestamp' => IndependentMarketBaseline::currentSnapshot()['captured_at'] ?? null,
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

        $reasons = [];
        $active = !$deleted && $status === 1;
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
     * @return array{rate:?string,source:?string,source_type:?string,provider:?string,symbol:?string,path:?string,age_seconds:?int,circular_source_detected:bool}
     */
    private function resolveBaseline(string $from, string $to): array
    {
        $empty = [
            'rate' => null,
            'source' => null,
            'source_type' => null,
            'provider' => null,
            'symbol' => null,
            'path' => null,
            'age_seconds' => null,
            'circular_source_detected' => false,
        ];
        try {
            $baseline = $this->baseline ?? new IndependentMarketBaseline();
            $asset = IndependentMarketBaseline::assetFromCode($from);
            if ($asset === null) {
                return $empty;
            }
            if ($asset === 'USDT' || $asset === 'USDC') {
                $q = $baseline->stableRub($asset);

                return $q ? [
                    'rate' => $q['rate'],
                    'source' => $q['source'],
                    'source_type' => $q['source_type'] ?? null,
                    'provider' => $q['provider'] ?? null,
                    'symbol' => $q['symbol'] ?? ($asset . 'USD*USDRUB'),
                    'path' => 'stable_to_rub',
                    'age_seconds' => $q['age_seconds'] ?? null,
                    'circular_source_detected' => !empty($q['circular_source_detected']),
                ] : $empty;
            }
            $q = $baseline->cryptoRub($asset);

            return $q ? [
                'rate' => $q['rate'],
                'source' => $q['source'],
                'source_type' => $q['source_type'] ?? null,
                'provider' => $q['provider'] ?? null,
                'symbol' => $q['symbol'] ?? ($asset . 'USDT*USDRUB'),
                'path' => 'crypto_to_rub',
                'age_seconds' => $q['age_seconds'] ?? null,
                'circular_source_detected' => !empty($q['circular_source_detected']),
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
