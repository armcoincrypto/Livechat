<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Services;

use App\Models\BestChangeDirection;
use iEXPackages\Calculator\Traits\InteractsWithNumbers;

/**
 * AntiFakeScoreService
 *
 * Считает качество строки rates (0..100) и причины штрафов.
 *
 * Идея:
 * - не только "отфильтровать", а оценить качество
 * - затем можно:
 *   - отбрасывать ниже порога
 *   - выбирать среди кандидатов лучшего по score
 */
final class AntiFakeScoreService
{
    use InteractsWithNumbers;

    private const SCALE = 18;

    /**
     * @return array{score:int,reasons:array<string,int>}
     */
    public function score(BestChangeDirection $direction, array $row, int $referenceAmountMode = 1): array
    {
        // Базовый score
        $score = 100;
        $reasons = [];

        $marks = $row['marks'] ?? [];
        if (!is_array($marks)) {
            $marks = [];
        }
        $marksSet = array_fill_keys(array_map('strval', $marks), true);

        // Жёсткие маркеры
        if (isset($marksSet['unstable'])) {
            $score -= 60;
            $reasons['unstable'] = 1;
        }
        if (isset($marksSet['delay'])) {
            $score -= 15;
            $reasons['delay'] = 1;
        }
        if (isset($marksSet['manual'])) {
            $score -= 10;
            $reasons['manual'] = 1;
        }
        if (isset($marksSet['verifying'])) {
            $score -= 10;
            $reasons['verifying'] = 1;
        }
        if (isset($marksSet['floating'])) {
            $score -= 8;
            $reasons['floating'] = 1;
        }

        // Extra fees (fromfee/tofee)
        $extra = $row['extra'] ?? [];
        if (is_array($extra) && ($extra !== [])) {
            if (isset($extra['fromfee']) || isset($extra['tofee'])) {
                $score -= 12;
                $reasons['extra_fees'] = 1;
            }
        }

        // Лимиты: inmin/inmax
        $inMin = $this->toBcString($row['inmin'] ?? '0', self::SCALE);
        $inMax = $this->toBcString($row['inmax'] ?? '0', self::SCALE);

        // reference amount: берём min_sum направления, если есть
        $referenceAmount = $this->toBcString($direction->min_sum ?? '0', self::SCALE);
        $hasRef = $this->compareValues($referenceAmount, '0', self::SCALE) === 1;

        if ($hasRef) {
            // если reference меньше inmin — строка не подходит для нашей суммы
            if ($this->compareValues($referenceAmount, $inMin, self::SCALE) === -1) {
                $score -= 30;
                $reasons['fake_limits_min'] = 1;
            }
            // если reference больше inmax — строка не подходит для нашей суммы
            if ($this->compareValues($referenceAmount, $inMax, self::SCALE) === 1) {
                $score -= 30;
                $reasons['fake_limits_max'] = 1;
            }
        }

        // reserve vs inmax (если inmax > 0)
        $reserve = $this->toBcString($row['reserve'] ?? '0', self::SCALE);
        if ($this->compareValues($inMax, '0', self::SCALE) === 1) {
            // reserve < inmax — подозрительно (могут дать курс только на крошечную сумму)
            if ($this->compareValues($reserve, $inMax, self::SCALE) === -1) {
                $score -= 10;
                $reasons['low_reserve_vs_inmax'] = 1;
            }
        }

        // clamp 0..100
        $score = max(0, min(100, $score));

        return ['score' => $score, 'reasons' => $reasons];
    }
}
