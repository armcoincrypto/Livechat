<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Services;

use App\Models\BestChangeDirection;
use App\Settings\BestChangeConfig;
use iEXPackages\BestChange\DTO\RateSelectionPolicy;

/**
 * RateSelectionPolicyResolver
 *
 * Собирает политику выбора курса для конкретного направления.
 *
 * Источники:
 * - typePosition: BestChangeConfig.type_position (rate|rankrate)
 * - sortOrder и positionWindowSeconds: config/courses.php (engine)
 * - mode/topN: BestChangeDirection.rate_mode/top_n или BestChangeConfig.rate_mode/top_n
 */
final class RateSelectionPolicyResolver
{
    public function resolve(BestChangeDirection $direction, BestChangeConfig $config): RateSelectionPolicy
    {
        $type = strtolower(trim($config->typePosition()));
        $type = $type === 'rate' ? 'rate' : 'rankrate';

        $sortOrder = strtolower((string) config('courses.bestchange.engine.sort_order', 'api'));
        if (!in_array($sortOrder, ['api','asc','desc'], true)) {
            $sortOrder = 'api';
        }

        $window = (int) config('courses.bestchange.engine.position_window_seconds', 600);
        $window = max(60, min(86400, $window));

        $mode = $direction->rate_mode !== null
            ? strtolower(trim((string) $direction->rate_mode))
            : $config->rateMode();

        if (!in_array($mode, ['position','median_top_n','weighted_avg_top_n'], true)) {
            $mode = $config->rateMode();
        }

        $topN = $direction->top_n !== null ? (int) $direction->top_n : $config->topN();
        $topN = max(1, min(100, $topN));

        return new RateSelectionPolicy(
            typeField: $type,
            sortOrder: $sortOrder,
            mode: $mode,
            topN: $topN,
            positionWindowSeconds: $window,
        );
    }
}
