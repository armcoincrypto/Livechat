<?php

declare(strict_types=1);

namespace App\Services\Rates;

/**
 * Shared Top-N book normalization used by DirectionExchangeRecalculateService
 * and L1 shadow parity tests.
 *
 * Top-N adjust() only consumes changer + rate after ASC sort by rate.
 */
final class BestChangeTopNBookHelper
{
    public const SCALE = 18;

    /**
     * @param  array<int, array<string, mixed>>  $rawRows
     * @return array<int, array{changer: int, rate: string}>
     */
    public static function normalizeForTopN(array $rawRows): array
    {
        $out = [];
        foreach ($rawRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $changer = (int) ($row['changer'] ?? 0);
            $rate = (string) ($row['rate'] ?? '0');
            if ($changer <= 0) {
                continue;
            }
            if (! is_numeric($rate) || bccomp($rate, '0', self::SCALE) !== 1) {
                continue;
            }
            $out[] = [
                'changer' => $changer,
                'rate' => $rate,
            ];
        }

        usort(
            $out,
            static fn (array $a, array $b): int => bccomp($a['rate'], $b['rate'], self::SCALE)
        );

        return array_values($out);
    }

    /**
     * Stable fingerprint of Top-N-relevant book content (order-independent via normalize).
     *
     * @param  array<int, array<string, mixed>>  $rawRows
     */
    public static function fingerprint(array $rawRows): string
    {
        $norm = self::normalizeForTopN($rawRows);
        $parts = [];
        foreach ($norm as $row) {
            $parts[] = $row['changer'].':'.$row['rate'];
        }

        return hash('sha256', implode('|', $parts));
    }

    /**
     * @param  array<int, array<string, mixed>>  $a
     * @param  array<int, array<string, mixed>>  $b
     */
    public static function topNRelevantEqual(array $a, array $b): bool
    {
        return self::fingerprint($a) === self::fingerprint($b);
    }
}
