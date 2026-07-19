<?php

declare(strict_types=1);

namespace App\Services\Rates;

/**
 * Selects a valid peer rate from a normalized sample.
 *
 * Does not invent rates. Insufficient or outlier samples fail closed.
 */
final class PeerRateSelector
{
    public const DEFAULT_MAX_MEDIAN_DEVIATION = '0.12';

    public const MIN_SAMPLE = 3;

    public const SCALE = 18;

    public function __construct(
        private readonly RateSanityGuard $guard = new RateSanityGuard(),
    ) {
    }

    /**
     * @param list<string> $peerRates Already orientation-normalized positive decimal strings
     * @param string|null $preferred Optional preferred peer (e.g. position_num selection)
     * @return array{
     *   ok: bool,
     *   selected_rate: string|null,
     *   peer_identifier: string|null,
     *   sample_size: int,
     *   median: string|null,
     *   deviation_from_median: string|null,
     *   reason: string
     * }
     */
    public function selectValidPeerRate(
        array $peerRates,
        ?string $preferred = null,
        string $maxMedianDeviation = self::DEFAULT_MAX_MEDIAN_DEVIATION,
        int $minSample = self::MIN_SAMPLE,
    ): array {
        $peers = [];
        foreach ($peerRates as $idx => $raw) {
            $n = $this->guard->normalize((string) $raw);
            if ($n !== null) {
                $peers[] = ['id' => (string) $idx, 'rate' => $n];
            }
        }

        $sampleSize = count($peers);
        if ($sampleSize < $minSample) {
            return [
                'ok' => false,
                'selected_rate' => null,
                'peer_identifier' => null,
                'sample_size' => $sampleSize,
                'median' => null,
                'deviation_from_median' => null,
                'reason' => 'insufficient_peer_sample',
            ];
        }

        $sorted = array_column($peers, 'rate');
        sort($sorted, SORT_STRING);
        $median = $this->median($sorted);
        if ($median === null) {
            return [
                'ok' => false,
                'selected_rate' => null,
                'peer_identifier' => null,
                'sample_size' => $sampleSize,
                'median' => null,
                'deviation_from_median' => null,
                'reason' => 'invalid_median',
            ];
        }

        $preferredNorm = $preferred !== null ? $this->guard->normalize($preferred) : null;
        $candidate = null;
        $candidateId = null;

        if ($preferredNorm !== null) {
            foreach ($peers as $peer) {
                if ($peer['rate'] === $preferredNorm) {
                    $candidate = $preferredNorm;
                    $candidateId = $peer['id'];
                    break;
                }
            }
            if ($candidate === null) {
                $candidate = $preferredNorm;
                $candidateId = 'preferred';
            }
        } else {
            // Prefer median itself (robust), not the richest outlier.
            $candidate = $median;
            $candidateId = 'median';
        }

        $deviation = bcdiv(bcsub($candidate, $median, self::SCALE), $median, self::SCALE);
        $absDev = $this->absBc($deviation);
        $maxDev = $this->guard->normalize($maxMedianDeviation) ?? self::DEFAULT_MAX_MEDIAN_DEVIATION;

        if (bccomp($absDev, $maxDev, self::SCALE) === 1) {
            return [
                'ok' => false,
                'selected_rate' => null,
                'peer_identifier' => $candidateId,
                'sample_size' => $sampleSize,
                'median' => $median,
                'deviation_from_median' => $deviation,
                'reason' => 'outlier_peer_rejected',
            ];
        }

        return [
            'ok' => true,
            'selected_rate' => $candidate,
            'peer_identifier' => $candidateId,
            'sample_size' => $sampleSize,
            'median' => $median,
            'deviation_from_median' => $deviation,
            'reason' => 'accepted',
        ];
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

    private function absBc(string $value): string
    {
        return bccomp($value, '0', self::SCALE) === -1
            ? bcmul($value, '-1', self::SCALE)
            : $value;
    }
}
