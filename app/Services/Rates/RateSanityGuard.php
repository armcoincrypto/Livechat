<?php

declare(strict_types=1);

namespace App\Services\Rates;

/**
 * RateSanityGuard
 *
 * Validates a candidate published rate against a reconstructed market baseline
 * derived from BestChange filtered peer rates (after the same 1/field inversion
 * used by RateCalculator).
 *
 * Units:
 * - $maxDeviationFraction is a fraction of baseline (0.12 = 12%), never percent points.
 * - Rates must be finite positive decimals.
 */
final class RateSanityGuard
{
    public const DEFAULT_MAX_DEVIATION_FRACTION = '0.12';

    public const SCALE = 18;

    /**
     * @param list<string> $peerRates Positive decimal strings in the same orientation as $candidate
     * @return array{
     *   ok: bool,
     *   action: 'pass'|'clamp'|'reject',
     *   candidate: string,
     *   baseline: string|null,
     *   ratio: string|null,
     *   effective: string,
     *   reason: string|null
     * }
     */
    public function evaluate(
        string $candidate,
        array $peerRates,
        string $maxDeviationFraction = self::DEFAULT_MAX_DEVIATION_FRACTION,
        bool $clamp = true
    ): array {
        $candidate = $this->normalize($candidate);
        if ($candidate === null) {
            return [
                'ok' => false,
                'action' => 'reject',
                'candidate' => '0',
                'baseline' => null,
                'ratio' => null,
                'effective' => '0',
                'reason' => 'non_positive_or_invalid_candidate',
            ];
        }

        $peers = [];
        foreach ($peerRates as $peer) {
            $n = $this->normalize((string) $peer);
            if ($n !== null) {
                $peers[] = $n;
            }
        }

        if ($peers === []) {
            return [
                'ok' => true,
                'action' => 'pass',
                'candidate' => $candidate,
                'baseline' => null,
                'ratio' => null,
                'effective' => $candidate,
                'reason' => 'no_peer_baseline',
            ];
        }

        sort($peers, SORT_STRING);
        $baseline = $this->median($peers);
        if ($baseline === null || bccomp($baseline, '0', self::SCALE) !== 1) {
            return [
                'ok' => true,
                'action' => 'pass',
                'candidate' => $candidate,
                'baseline' => null,
                'ratio' => null,
                'effective' => $candidate,
                'reason' => 'invalid_baseline',
            ];
        }

        $ratio = bcdiv($candidate, $baseline, self::SCALE);
        $maxRatio = bcadd('1', $this->normalizeFraction($maxDeviationFraction), self::SCALE);
        $minRatio = bcsub('1', $this->normalizeFraction($maxDeviationFraction), self::SCALE);
        if (bccomp($minRatio, '0', self::SCALE) !== 1) {
            $minRatio = '0.01';
        }

        if (bccomp($ratio, $maxRatio, self::SCALE) === 1) {
            $clamped = bcmul($baseline, $maxRatio, self::SCALE);
            return [
                'ok' => $clamp,
                'action' => $clamp ? 'clamp' : 'reject',
                'candidate' => $candidate,
                'baseline' => $baseline,
                'ratio' => $ratio,
                'effective' => $clamp ? $clamped : '0',
                'reason' => 'above_max_deviation',
            ];
        }

        if (bccomp($ratio, $minRatio, self::SCALE) === -1) {
            // Extremely low rates are usually commercial spread, not inflation —
            // pass but annotate. Callers may still log.
            return [
                'ok' => true,
                'action' => 'pass',
                'candidate' => $candidate,
                'baseline' => $baseline,
                'ratio' => $ratio,
                'effective' => $candidate,
                'reason' => 'below_min_deviation_allowed',
            ];
        }

        return [
            'ok' => true,
            'action' => 'pass',
            'candidate' => $candidate,
            'baseline' => $baseline,
            'ratio' => $ratio,
            'effective' => $candidate,
            'reason' => null,
        ];
    }

    /**
     * Invert BestChange API field values the same way RateCalculator does: 1/field.
     *
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    public function peerRatesFromBestChangeRows(array $rows, string $typeField = 'rate'): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $raw = $row[$typeField] ?? null;
            if (!is_scalar($raw)) {
                continue;
            }
            $field = $this->normalize((string) $raw);
            if ($field === null) {
                continue;
            }
            $inverted = bcdiv('1', $field, self::SCALE);
            $n = $this->normalize($inverted);
            if ($n !== null) {
                $out[] = $n;
            }
        }
        return $out;
    }

    public function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim(str_replace(',', '.', $value));
        if ($value === '' || !is_numeric($value)) {
            return null;
        }
        if (!is_finite((float) $value)) {
            return null;
        }
        $normalized = number_format((float) $value, self::SCALE, '.', '');
        // Prefer bc for precision when possible
        try {
            $normalized = bcmul($value, '1', self::SCALE);
        } catch (\Throwable) {
            // keep number_format fallback
        }
        if (bccomp($normalized, '0', self::SCALE) !== 1) {
            return null;
        }
        return $normalized;
    }

    private function normalizeFraction(string $fraction): string
    {
        $n = $this->normalize($fraction);
        return $n ?? self::DEFAULT_MAX_DEVIATION_FRACTION;
    }

    /**
     * @param list<string> $sortedPositive
     */
    private function median(array $sortedPositive): ?string
    {
        $count = count($sortedPositive);
        if ($count === 0) {
            return null;
        }
        $mid = intdiv($count, 2);
        if ($count % 2 === 1) {
            return $sortedPositive[$mid];
        }
        return bcdiv(
            bcadd($sortedPositive[$mid - 1], $sortedPositive[$mid], self::SCALE),
            '2',
            self::SCALE
        );
    }
}
