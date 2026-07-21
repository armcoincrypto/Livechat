<?php

declare(strict_types=1);

namespace App\Services\Rates;

use Throwable;

/**
 * Operator-gated RUB payment-family premium policy (canonical).
 *
 * approved=true required for coin→RUB public export certification.
 * Family hard maximum is a ceiling only — never auto-increase configured premiums.
 */
final class RubFamilyPremiumPolicy
{
    public const CRYPTO_MAX_AGE_SECONDS = 900;

    public function __construct(
        private readonly ?array $config = null,
    ) {
    }

    public static function fromStorageApp(): self
    {
        $paths = [
            base_path('resources/rates/rub-family-premium-policy.json'),
            storage_path('app/rates/rub-family-premium-policy.json'),
        ];
        foreach ($paths as $path) {
            if (!is_file($path)) {
                continue;
            }
            try {
                $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    return new self($decoded);
                }
            } catch (Throwable) {
            }
        }

        return new self([
            'approved' => false,
            'families' => [],
            'default_thresholds' => [
                'unexplained_warning_percent' => 1.0,
                'unexplained_block_percent' => 2.0,
            ],
        ]);
    }

    public function isApproved(): bool
    {
        return !empty($this->config['approved']);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function familyForDestination(string $toCode): ?array
    {
        $to = strtoupper(trim($toCode));
        if ($to === '' || !str_contains($to, 'RUB')) {
            return null;
        }
        $families = $this->config['families'] ?? [];
        if (!is_array($families)) {
            return null;
        }
        foreach ($families as $key => $family) {
            if (!is_array($family) || $key === 'OTHER_RUB') {
                continue;
            }
            foreach ($family['destination_codes'] ?? [] as $code) {
                if (strtoupper((string) $code) === $to) {
                    return $family + ['family_key' => (string) $key];
                }
            }
        }
        $other = $families['OTHER_RUB'] ?? null;

        return is_array($other) ? ($other + ['family_key' => 'OTHER_RUB']) : null;
    }

    public function isFamilyExportAllowed(string $toCode): bool
    {
        if (!$this->isApproved()) {
            return false;
        }
        $family = $this->familyForDestination($toCode);
        if ($family === null) {
            return false;
        }
        $decision = (string) ($family['decision'] ?? $family['proposed_decision'] ?? '');
        if ($decision === 'KEEP_BLOCKED' || $decision === 'DEPRECATE') {
            return false;
        }

        return (bool) ($family['export_allowed_when_approved'] ?? false);
    }

    public function isFamilyOrderAllowed(string $toCode): bool
    {
        if (!$this->isApproved()) {
            return false;
        }
        $family = $this->familyForDestination($toCode);
        if ($family === null) {
            return false;
        }
        $decision = (string) ($family['decision'] ?? $family['proposed_decision'] ?? '');
        if ($decision === 'KEEP_BLOCKED' || $decision === 'DEPRECATE') {
            return false;
        }

        return (bool) ($family['order_allowed_when_approved'] ?? false);
    }

    /**
     * Target-band top used as policy-calculated expected commercial premium (not hard max).
     * Does not mutate direction.profit.
     */
    public function targetPremiumMaxPercent(string $toCode): ?float
    {
        if (!$this->isApproved()) {
            return null;
        }
        $family = $this->familyForDestination($toCode);
        if ($family === null || !$this->isFamilyExportAllowed($toCode)) {
            return null;
        }
        $v = $family['target_premium_max_percent'] ?? $family['target_premium_percent'] ?? null;

        return is_numeric($v) ? (float) $v : null;
    }

    /**
     * Approved source-rate premium used before the existing calculator applies
     * direction-specific profit and other configured adjustments.
     *
     * This does not mutate direction.profit and never exceeds the approved
     * target-band ceiling.
     */
    public function canonicalSourcePremiumPercent(string $toCode): ?float
    {
        return $this->targetPremiumMaxPercent($toCode);
    }

    public function hardMaximumPremiumPercent(string $toCode): ?float
    {
        $family = $this->familyForDestination($toCode);
        if ($family === null) {
            return null;
        }
        $v = $family['hard_maximum_premium_percent']
            ?? $family['maximum_explained_premium_percent']
            ?? null;

        return is_numeric($v) ? (float) $v : null;
    }

    public function warningPremiumPercent(string $toCode): ?float
    {
        $family = $this->familyForDestination($toCode);
        if ($family === null) {
            return null;
        }
        $v = $family['warning_premium_percent'] ?? null;

        return is_numeric($v) ? (float) $v : null;
    }

    /**
     * @return array{
     *   classification: string,
     *   export_allowed: bool,
     *   order_allowed: bool,
     *   raw_premium_percent: float|null,
     *   expected_premium_percent: float|null,
     *   unexplained_vs_expected_percent: float|null,
     *   configured_profit_percent: float,
     *   reasons: list<string>,
     *   family_key: string|null
     * }
     */
    public function evaluateCoinRub(
        string $toCode,
        ?float $rawPremiumVsMidPercent,
        float $configuredProfitPercent = 0.0,
    ): array {
        $family = $this->familyForDestination($toCode);
        $reasons = [];
        $base = [
            'classification' => 'NO_POLICY',
            'export_allowed' => false,
            'order_allowed' => false,
            'raw_premium_percent' => $rawPremiumVsMidPercent,
            'expected_premium_percent' => null,
            'unexplained_vs_expected_percent' => null,
            'configured_profit_percent' => $configuredProfitPercent,
            'reasons' => &$reasons,
            'family_key' => $family['family_key'] ?? null,
        ];

        if (!$this->isApproved()) {
            $reasons[] = 'rub_policy_not_approved';

            return $base;
        }
        if ($family === null) {
            $reasons[] = 'family_not_mapped';
            $base['classification'] = 'NO_POLICY';

            return $base;
        }
        if (!$this->isFamilyExportAllowed($toCode)) {
            $reasons[] = 'family_keep_blocked';
            $base['classification'] = 'NO_POLICY';

            return $base;
        }

        $hardMax = $this->hardMaximumPremiumPercent($toCode) ?? 0.0;
        $warnPrem = $this->warningPremiumPercent($toCode) ?? $hardMax;
        $targetMax = $this->targetPremiumMaxPercent($toCode) ?? 0.0;
        $unexplWarn = (float) (($this->config['default_thresholds']['unexplained_warning_percent'] ?? 1.0));
        $unexplBlock = (float) (($this->config['default_thresholds']['unexplained_block_percent'] ?? 2.0));

        // Ceiling on configured profit — never auto-raise profit to target/max.
        if ($configuredProfitPercent - $hardMax > 1e-9) {
            $reasons[] = 'configured_premium_exceeds_family_hard_maximum';
            $base['classification'] = 'QUARANTINE_REQUIRED';

            return $base;
        }

        if ($rawPremiumVsMidPercent === null) {
            $reasons[] = 'no_independent_baseline';
            $base['classification'] = 'NO_BASELINE';

            return $base;
        }

        $base['expected_premium_percent'] = $targetMax;
        // Policy expected commercial rate sits at target-band top (ceiling for "explained"),
        // not at hard maximum and not an automatic markup applied to storage.
        $unexplained = $rawPremiumVsMidPercent - $targetMax;
        $base['unexplained_vs_expected_percent'] = $unexplained;

        if ($rawPremiumVsMidPercent - $hardMax > 1e-9) {
            $reasons[] = 'raw_premium_exceeds_hard_maximum';
            $base['classification'] = 'QUARANTINE_REQUIRED';

            return $base;
        }

        if ($unexplained - $unexplBlock > 1e-9) {
            $reasons[] = 'unexplained_above_expected_block';
            $base['classification'] = 'QUARANTINE_REQUIRED';

            return $base;
        }

        if ($rawPremiumVsMidPercent - $warnPrem > 1e-9 || $unexplained - $unexplWarn > 1e-9) {
            $reasons[] = 'premium_or_unexplained_warning_band';
            $base['classification'] = 'REVIEW';
            // Public surfaces: REVIEW blocks order and export (quote may still be shown).
            $base['order_allowed'] = false;
            $base['export_allowed'] = false;

            return $base;
        }

        if ($rawPremiumVsMidPercent > 1e-9 && $rawPremiumVsMidPercent <= $targetMax + 1e-9) {
            $base['classification'] = 'PASS_EXPLAINED_SPREAD';
        } else {
            $base['classification'] = 'PASS';
        }
        $base['export_allowed'] = true;
        $base['order_allowed'] = $this->isFamilyOrderAllowed($toCode);

        return $base;
    }

    /**
     * Backward-compatible helper: hard-max ceiling when approved.
     */
    public function explainedPremiumPercent(string $toCode): ?float
    {
        return $this->targetPremiumMaxPercent($toCode);
    }

    public function unexplainedVersusApprovedBand(?float $rawMarketDeviationPercent, string $toCode): ?float
    {
        if ($rawMarketDeviationPercent === null) {
            return null;
        }
        $target = $this->targetPremiumMaxPercent($toCode);
        if ($target === null) {
            return null;
        }

        return $rawMarketDeviationPercent - $target;
    }

    /**
     * @return array{pass:float,review:float,critical:float}
     */
    public function thresholdsForDestination(string $toCode): array
    {
        $warn = (float) (($this->config['default_thresholds']['unexplained_warning_percent'] ?? 1.0));
        $block = (float) (($this->config['default_thresholds']['unexplained_block_percent'] ?? 2.0));

        return ['pass' => $warn, 'review' => $block, 'critical' => $block];
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $families = is_array($this->config['families'] ?? null) ? $this->config['families'] : [];

        return [
            'approved' => $this->isApproved(),
            'approved_at' => $this->config['approved_at'] ?? null,
            'approved_by' => $this->config['approved_by'] ?? null,
            'family_count' => count($families),
            'version' => $this->config['version'] ?? null,
            'schema' => $this->config['schema'] ?? null,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function raw(): array
    {
        return $this->config ?? [];
    }
}
