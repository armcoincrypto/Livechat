<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Services;

use App\Models\BestChangeDirection;

/**
 * PositionResolver
 *
 * Вычисляет позицию обменника:
 * - "5" -> 5
 * - "3-7" -> стабильная позиция внутри окна времени
 * - иначе -> default
 * Затем clamp в пределах [1..count].
 */
final class PositionResolver
{
    public function resolve(BestChangeDirection $direction, int $count, int $defaultPos, int $windowSeconds): int
    {
        if ($count <= 0) return 1;

        $raw = trim((string) $direction->position_num);
        $position = null;

        if ($raw !== '' && str_contains($raw, '-')) {
            [$a, $b] = array_map('trim', explode('-', $raw, 2));
            if (is_numeric($a) && is_numeric($b)) {
                $min = max(1, (int)$a);
                $max = max(1, (int)$b);
                if ($min > $max) [$min, $max] = [$max, $min];

                $position = $this->stableRandom((int)$direction->id, $min, $max, $windowSeconds);
            }
        }

        if ($position === null && $raw !== '' && is_numeric($raw)) {
            $p = (int)$raw;
            if ($p >= 1) $position = $p;
        }

        if ($position === null) {
            $position = max(1, $defaultPos);
        }

        $position = max(1, min($count, $position));
        return $position;
    }

    private function stableRandom(int $id, int $min, int $max, int $windowSeconds): int
    {
        if ($min >= $max) return $min;

        $w = max(60, $windowSeconds);
        $win = (int) floor(time() / $w);
        $seed = crc32($id . ':' . $win);

        $span = $max - $min + 1;
        return $min + (int)($seed % $span);
    }
}
