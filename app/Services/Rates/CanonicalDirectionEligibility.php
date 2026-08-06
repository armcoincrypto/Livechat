<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Models\DirectionExchange;

/**
 * C3-A: context-aware façade over RateDirectionEligibility.
 *
 * Core rule: CATALOG/SELECTOR eligibility must not be weaker than QUOTE/ORDER
 * for public advertisement. BestChange allowlist applies only after core pass.
 *
 * Courses catalog uses {@see passesPublicCatalogPrefilter()} — the same
 * duplicate + positive-course gates that CTX_CATALOG requires — without
 * forking a second exclusion list.
 */
final class CanonicalDirectionEligibility
{
    public const CTX_CATALOG = 'CATALOG';
    public const CTX_SELECTOR = 'SELECTOR';
    public const CTX_QUOTE = 'QUOTE';
    public const CTX_ORDER = 'ORDER';
    public const CTX_BESTCHANGE_EXPORT = 'BESTCHANGE_EXPORT';

    public function __construct(
        private readonly RateDirectionEligibility $eligibility,
    ) {
    }

    public static function make(): self
    {
        return new self(RateDirectionEligibility::make());
    }

    /**
     * Shared catalog/selector prefilter used by Courses adjacency builder.
     * Must stay aligned with CTX_CATALOG gates for duplicates and course_value.
     */
    public static function passesPublicCatalogPrefilter(int $directionId, mixed $courseValue): bool
    {
        if ($directionId <= 0 || PublicDuplicateExclusion::isExcluded($directionId)) {
            return false;
        }

        $course = is_string($courseValue) || is_numeric($courseValue)
            ? (string) $courseValue
            : '';

        return $course !== '' && is_numeric($course) && (float) $course > 0.0;
    }

    /**
     * @return array{
     *   eligible:bool,
     *   canonical_direction_id:int|null,
     *   reason_code:string|null,
     *   rate_fresh:bool,
     *   provider_available:bool,
     *   limits_valid:bool,
     *   reserve_valid:bool,
     *   currencies_visible:bool,
     *   duplicate_status:string,
     *   quote_allowed:bool,
     *   order_allowed:bool,
     *   export_allowed:bool,
     *   BestChange_allowed:bool,
     *   raw:array<string,mixed>
     * }
     */
    public function evaluate(DirectionExchange $direction, string $context): array
    {
        $direction->loadMissing(['currency1', 'currency2']);
        $raw = $this->eligibility->evaluateDirection($direction);
        $id = (int) ($direction->id ?? 0);
        $duplicate = PublicDuplicateExclusion::isExcluded($id)
            ? 'excluded'
            : 'ok';
        $replacement = PublicDuplicateExclusion::canonicalReplacement($id);

        $currenciesVisible = ((int) ($direction->currency1?->status ?? -1) === 0)
            && ((int) ($direction->currency2?->status ?? -1) === 0);

        $course = (string) ($direction->course_value ?? '');
        $courseAndNotDuplicate = self::passesPublicCatalogPrefilter($id, $direction->course_value);

        $quote = !empty($raw['quote_allowed']);
        $order = !empty($raw['order_allowed']);
        $export = !empty($raw['export_allowed']) && !empty($raw['BestChange_allowed']);

        $eligible = match ($context) {
            self::CTX_CATALOG, self::CTX_SELECTOR => $quote && $order && $currenciesVisible && $courseAndNotDuplicate,
            self::CTX_QUOTE => $quote,
            self::CTX_ORDER => $order,
            self::CTX_BESTCHANGE_EXPORT => $export,
            default => false,
        };

        $reason = null;
        if (!$eligible) {
            if ($duplicate === 'excluded') {
                $reason = 'CANONICAL_DUPLICATE';
            } elseif ($course === '' || !is_numeric($course) || (float) $course <= 0.0) {
                $reason = 'ZERO_RATE';
            } elseif (!$currenciesVisible) {
                $reason = 'CURRENCY_HIDDEN';
            } else {
                $reason = (string) ($raw['classification'] ?? $raw['error_code'] ?? 'INELIGIBLE');
            }
        }

        return [
            'eligible' => $eligible,
            'canonical_direction_id' => $replacement ?? ($eligible ? $id : null),
            'reason_code' => $reason,
            'rate_fresh' => ($raw['baseline_status'] ?? '') !== 'stale' && ($raw['baseline_status'] ?? '') !== 'missing',
            'provider_available' => empty($raw['force_block_reason'] ?? null),
            'limits_valid' => true,
            'reserve_valid' => in_array((string) ($raw['reserve_status'] ?? ''), ['adequate', 'not_required', 'not_applicable'], true),
            'currencies_visible' => $currenciesVisible,
            'duplicate_status' => $duplicate,
            'quote_allowed' => $quote,
            'order_allowed' => $order,
            'export_allowed' => !empty($raw['export_allowed']),
            'BestChange_allowed' => !empty($raw['BestChange_allowed']),
            'raw' => $raw,
        ];
    }
}
