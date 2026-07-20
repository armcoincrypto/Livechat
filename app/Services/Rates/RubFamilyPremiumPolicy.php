<?php

declare(strict_types=1);

namespace App\Services\Rates;

use Throwable;

/**
 * Operator-gated RUB payment-family premium policy.
 *
 * Until approved=true, proposed premiums MUST NOT explain deviations for
 * BestChange certification or public re-enable decisions.
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
                // try next path
            }
        }

        return new self([
            'approved' => false,
            'families' => [],
            'default_thresholds' => [
                'pass_unexplained_pct' => 3.0,
                'review_unexplained_pct' => 7.0,
                'critical_unexplained_pct' => 7.0,
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
     * Explained family premium percent to add on top of direction.profit when policy is approved.
     * Returns null when policy is not approved (premium must not silently explain outliers).
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
        $max = $family['proposed_configured_premium_max_pct'] ?? null;

        return is_numeric($max) ? (float) $max : null;
    }

    /**
     * @return array{pass:float,review:float,critical:float}
     */
    public function thresholdsForDestination(string $toCode): array
    {
        $defaults = $this->config['default_thresholds'] ?? [];
        $pass = (float) ($defaults['pass_unexplained_pct'] ?? 3.0);
        $review = (float) ($defaults['review_unexplained_pct'] ?? 7.0);
        $critical = (float) ($defaults['critical_unexplained_pct'] ?? 7.0);

        if ($this->isApproved()) {
            $family = $this->familyForDestination($toCode);
            if ($family !== null) {
                $pass = min($pass, (float) ($family['proposed_warning_unexplained_pct'] ?? $pass));
                $review = (float) ($family['proposed_warning_unexplained_pct'] ?? $review);
                $critical = (float) ($family['proposed_critical_unexplained_pct'] ?? $critical);
            }
        }

        return ['pass' => $pass, 'review' => $review, 'critical' => $critical];
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        return [
            'approved' => $this->isApproved(),
            'approved_at' => $this->config['approved_at'] ?? null,
            'approved_by' => $this->config['approved_by'] ?? null,
            'family_count' => is_array($this->config['families'] ?? null) ? count($this->config['families']) : 0,
            'version' => $this->config['version'] ?? null,
        ];
    }
}
