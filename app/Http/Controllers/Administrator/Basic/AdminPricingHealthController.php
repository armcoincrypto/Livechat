<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Models\DirectionExchange;
use App\Services\Rates\AdminPricingTransparency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only pricing health + commercial inventory for admin transparency.
 * Does not mutate rates, profit, fees, ownership, or positions.
 */
final class AdminPricingHealthController extends Controller
{
    public function show(): JsonResponse
    {
        $snap = AdminPricingTransparency::healthSnapshot();

        return response()->json([
            'data' => [
                'healthy' => $snap['healthy'],
                'ownership_counts' => $snap['ownership_counts'],
                'zero_profit' => $snap['zero_profit'],
                'stale_critical_count' => $snap['stale_critical_count'],
                'aging_count' => $snap['aging_count'] ?? 0,
                'last_bestchange_update_at' => $snap['last_bestchange_update_at'],
                'last_bestchange_update_age_seconds' => $snap['last_bestchange_update_age_seconds'],
                'last_bestchange_update_human' => $snap['last_bestchange_update_human'],
                'global_bestchange_position' => $snap['global_bestchange_position'],
                'global_rate_mode' => $snap['global_rate_mode'],
                'duplicate_mapping_groups' => $snap['duplicate_mapping_groups'],
                'duplicate_both_active' => $snap['duplicate_both_active'],
                'runtime_ambiguity' => $snap['runtime_ambiguity'],
                'generated_at' => $snap['generated_at'],
            ],
        ]);
    }

    /**
     * Pair-level «Почему такой курс?» explanation (read-only).
     */
    public function explain(int $id): JsonResponse
    {
        $item = DirectionExchange::query()
            ->with(['bestchange_directions', 'currency1:id,designation_xml', 'currency2:id,designation_xml'])
            ->findOrFail($id);

        return response()->json([
            'data' => AdminPricingTransparency::explain($item),
        ]);
    }

    /**
     * Commercial inventory ids for owner filters (read-only, capped).
     */
    public function inventory(Request $request): JsonResponse
    {
        $filter = (string) $request->query('filter', 'zero_profit');
        $q = DirectionExchange::query()->where('status', 1)->whereNull('deleted_at');

        match ($filter) {
            'zero_profit' => $q->where(function ($w) {
                $w->where(function ($b) {
                    $b->where('parser_source_name', 'BestChange')
                        ->whereRaw('CAST(COALESCE(profit,0) AS DECIMAL(20,8)) = 0');
                })->orWhere(function ($b) {
                    $b->where('parser_source_name', 'DERIVED_MARKET_BASELINE')
                        ->whereRaw('CAST(COALESCE(profit,0) AS DECIMAL(20,8)) = 0');
                })->orWhere(function ($b) {
                    $b->where('parser_source_name', 'ZELLE_USDTTRC20_BENCHMARK')
                        ->whereRaw('CAST(COALESCE(floating_fee,0) AS DECIMAL(20,8)) = 0');
                })->orWhere(function ($b) {
                    $b->whereIn('parser_source_name', ['Ручной курс', 'Manual'])
                        ->whereRaw('CAST(COALESCE(profit,0) AS DECIMAL(20,8)) = 0');
                });
            }),
            'manual' => $q->whereIn('parser_source_name', ['Ручной курс', 'Manual']),
            'zelle' => $q->where('parser_source_name', 'ZELLE_USDTTRC20_BENCHMARK'),
            'derived' => $q->where('parser_source_name', 'DERIVED_MARKET_BASELINE'),
            'bestchange' => $q->where('parser_source_name', 'BestChange'),
            'position_override' => $q->where('parser_source_name', 'BestChange')
                ->whereHas('bestchange_directions', function ($b) {
                    $global = (int) (new \App\Settings\BestChangeConfig())->position();
                    $b->where('status', 1)
                        ->whereRaw('CAST(position_num AS UNSIGNED) <> ?', [$global]);
                }),
            'aggressive_zelle_fee' => $q->where('parser_source_name', 'ZELLE_USDTTRC20_BENCHMARK')
                ->whereRaw('CAST(COALESCE(floating_fee,0) AS DECIMAL(20,8)) <= -6'),
            default => $q->whereRaw('1=0'),
        };

        $ids = $q->orderBy('id')->limit(500)->pluck('id');

        return response()->json([
            'data' => [
                'filter' => $filter,
                'count' => $ids->count(),
                'ids' => $ids->values(),
                'note' => $filter === 'zero_profit'
                    ? 'Явная прибыль 0% — не значит «без маржи»: позиция BestChange / fix_fee могут оставаться активны.'
                    : null,
            ],
        ]);
    }
}
