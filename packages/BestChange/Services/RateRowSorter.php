<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Services;

use iEXPackages\BestChange\DTO\RateSelectionPolicy;
use iEXPackages\Calculator\Traits\InteractsWithNumbers;

/**
 * RateRowSorter
 *
 * Детерминированная сортировка строк BestChange (/rates).
 *
 * Правила:
 * - sortOrder="api" трактуем как "asc" (у batch /rates порядок не гарантируется)
 * - сравнение только через BigDecimal (InteractsWithNumbers), без float
 * - tie-breaker: changer id (чтобы порядок был стабильным)
 */
final class RateRowSorter
{
    use InteractsWithNumbers;

    private const SCALE = 18;

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    public function sort(array $rows, RateSelectionPolicy $policy): array
    {
        if ($rows === []) {
            return [];
        }

        $field = (string) $policy->typeField; // rate|rankrate

        $order = strtolower(trim((string) $policy->sortOrder));
        $order = $order === 'desc' ? 'desc' : 'asc'; // api -> asc

        usort($rows, function (array $a, array $b) use ($field, $order): int {
            $avRaw = $a[$field] ?? '0';
            $bvRaw = $b[$field] ?? '0';

            $av = $this->toBcString(is_scalar($avRaw) ? $avRaw : '0', self::SCALE);
            $bv = $this->toBcString(is_scalar($bvRaw) ? $bvRaw : '0', self::SCALE);

            $cmp = $this->compareValues($av, $bv, self::SCALE);

            if ($cmp !== 0) {
                return $order === 'desc' ? -$cmp : $cmp;
            }

            // tie-breaker: changer id
            $ac = (int) ($a['changer'] ?? 0);
            $bc = (int) ($b['changer'] ?? 0);

            return $ac <=> $bc;
        });

        return array_values($rows);
    }
}
