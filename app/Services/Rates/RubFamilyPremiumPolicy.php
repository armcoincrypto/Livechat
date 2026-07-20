<?php

declare(strict_types=1);

namespace App\Services\Rates;

use Throwable;

/**
 * Operator-gated RUB payment-family premium policy (canonical).
 *
 * Until approved=true, proposed premiums MUST NOT explain deviations and
 * BestChange/public export certification for coin→RUB remains blocked.
 */
final class RubFamilyPremiumPolicy
{
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
                'warning_unexplained_percent' => 5.0,
                'critical_unexplained_percent' => 7.0,
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

    /**
     * Maximum explained family premium % when policy approved; null otherwise.
     */
    public function explainedPremiumPercent(string $toCode): ?float
    {
        if (!$this->isApproved()) {
            return null;
        }
        $family = $this->familyForDestination($toCode);
        if ($family === null) {
            return null;
        }
        if (($family['proposed_decision'] ?? '') === 'KEEP_BLOCKED'
            || ($family['export_allowed_when_approved'] ?? true) === false) {
            return null;
        }
        $max = $family['maximum_explained_premium_percent']
            ?? $family['proposed_configured_premium_max_pct']
            ?? null;

        return is_numeric($max) ? (float) $max : null;
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
        if (($family['proposed_decision'] ?? '') === 'KEEP_BLOCKED') {
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
        if (($family['proposed_decision'] ?? '') === 'KEEP_BLOCKED') {
            return false;
        }

        return (bool) ($family['order_allowed_when_approved'] ?? false);
    }

    /**
     * Unexplained % after subtracting the approved positive OTC band above mid.
     * Positive result ⇒ rate still too high vs approved band (treasury risk).
     * Null when policy not approved or premium unavailable.
     */
    public function unexplainedVersusApprovedBand(?float $rawMarketDeviationPercent, string $toCode): ?float
    {
        if ($rawMarketDeviationPercent === null) {
            return null;
        }
        $explained = $this->explainedPremiumPercent($toCode);
        if ($explained === null) {
            return null;
        }

        return $rawMarketDeviationPercent - $explained;
    }

    /**
     * @return array{pass:float,review:float,critical:float}
     */
    public function thresholdsForDestination(string $toCode): array
    {
        $defaults = $this->config['default_thresholds'] ?? [];
        $pass = (float) ($defaults['warning_unexplained_percent'] ?? $defaults['pass_unexplained_pct'] ?? 3.0);
        // Keep PASS band tighter than warning when using warning key as default.
        $passBand = min(3.0, $pass);
        $review = (float) ($defaults['warning_unexplained_percent'] ?? $defaults['review_unexplained_pct'] ?? 7.0);
        $critical = (float) ($defaults['critical_unexplained_percent'] ?? $defaults['critical_unexplained_pct'] ?? 7.0);

        if ($this->isApproved()) {
            $family = $this->familyForDestination($toCode);
            if ($family !== null) {
                $review = (float) ($family['warning_unexplained_percent']
                    ?? $family['proposed_warning_unexplained_pct']
                    ?? $review);
                $critical = (float) ($family['critical_unexplained_percent']
                    ?? $family['proposed_critical_unexplained_pct']
                    ?? $critical);
                $passBand = min($passBand, $review);
            }
        }

        return ['pass' => $passBand, 'review' => $review, 'critical' => $critical];
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
