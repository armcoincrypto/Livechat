<?php

declare(strict_types=1);

namespace App\Services\Rates;

/**
 * Fail-closed public export / quote quarantine decisions.
 *
 * Does not delete directions. Callers must apply the same decision to website
 * quotes and BestChange XML so surfaces stay consistent.
 */
final class RateExportQuarantine
{
    public const EXPORT_ALLOWED = 'EXPORT_ALLOWED';

    public const EXPORT_ALLOWED_CONFIGURED_SPREAD = 'EXPORT_ALLOWED_CONFIGURED_SPREAD';

    public const EXPORT_BLOCKED_INVALID = 'EXPORT_BLOCKED_INVALID';

    public const EXPORT_BLOCKED_STALE = 'EXPORT_BLOCKED_STALE';

    public const EXPORT_BLOCKED_OUTLIER = 'EXPORT_BLOCKED_OUTLIER';

    public const EXPORT_BLOCKED_MAPPING = 'EXPORT_BLOCKED_MAPPING';

    public function __construct(
        private readonly RateSanityGuard $guard = new RateSanityGuard(),
        private readonly RateConfiguredExpectation $expectation = new RateConfiguredExpectation(),
    ) {
    }

    /**
     * @param array<string,mixed> $context
     * @return array{status: string, allowed: bool, reason: string|null}
     */
    public function evaluate(string $courseValue, array $context = []): array
    {
        $course = $this->guard->normalize($courseValue);
        if ($course === null) {
            return [
                'status' => self::EXPORT_BLOCKED_INVALID,
                'allowed' => false,
                'reason' => 'non_positive_or_invalid_course',
            ];
        }

        if (!empty($context['force_block_reason'])) {
            $reason = (string) $context['force_block_reason'];
            $status = match ($reason) {
                'stale_source' => self::EXPORT_BLOCKED_STALE,
                'currency_mapping_mismatch' => self::EXPORT_BLOCKED_MAPPING,
                'outlier' => self::EXPORT_BLOCKED_OUTLIER,
                default => self::EXPORT_BLOCKED_OUTLIER,
            };

            return ['status' => $status, 'allowed' => false, 'reason' => $reason];
        }

        // Explicit reviewed exemption (direction id allow-list via context only).
        if (!empty($context['reviewed_exemption'])) {
            return [
                'status' => self::EXPORT_ALLOWED,
                'allowed' => true,
                'reason' => 'reviewed_exemption',
            ];
        }

        $baseline = isset($context['baseline']) ? (string) $context['baseline'] : null;
        $profit = (string) ($context['profit_percent'] ?? '0');
        $analysis = $this->expectation->analyze(
            baseline: $baseline,
            actual: $course,
            profitPercent: $profit,
            paymentSystemFeePercent: (string) ($context['payment_system_fee_percent'] ?? '0'),
            percentageCommission: (string) ($context['percentage_commission'] ?? '0'),
        );

        $unexplained = $analysis['unexplained_deviation'];
        if ($unexplained === null && $baseline === null) {
            // No independent/peer baseline: allow only if allow_export already gated elsewhere.
            return [
                'status' => self::EXPORT_ALLOWED,
                'allowed' => true,
                'reason' => 'no_baseline_pass_through',
            ];
        }

        if ($unexplained !== null && abs($unexplained) > 12.0) {
            return [
                'status' => self::EXPORT_BLOCKED_OUTLIER,
                'allowed' => false,
                'reason' => 'unexplained_extreme_deviation',
            ];
        }

        if ($unexplained !== null && abs($unexplained) > 7.0) {
            return [
                'status' => self::EXPORT_BLOCKED_OUTLIER,
                'allowed' => false,
                'reason' => 'unexplained_critical_deviation',
            ];
        }

        $raw = $analysis['raw_market_deviation'];
        if ($raw !== null && abs($raw) > 3.0 && $unexplained !== null && abs($unexplained) <= 1.0) {
            return [
                'status' => self::EXPORT_ALLOWED_CONFIGURED_SPREAD,
                'allowed' => true,
                'reason' => 'configured_spread_explains_deviation',
            ];
        }

        return [
            'status' => self::EXPORT_ALLOWED,
            'allowed' => true,
            'reason' => null,
        ];
    }

    public function isExportableCourse(?string $courseValue): bool
    {
        return $this->guard->normalize((string) ($courseValue ?? '')) !== null;
    }
}
