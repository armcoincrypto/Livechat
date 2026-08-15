<?php

namespace iEXPackages\Analytics\Http\Controllers\Partners;

use App\Http\Controllers\Controller;
use iEXPackages\ReferralSystem\Models\ReferralRelationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReferralReferralsAnalyticsController extends Controller
{
    /**
     * Общая сводка по рефералам
     */
    public function summary(Request $request): JsonResponse
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->get('from'))->startOfDay()
            : null;

        $to = $request->filled('to')
            ? Carbon::parse($request->get('to'))->endOfDay()
            : null;

        $baseQuery = ReferralRelationship::query();

        if ($from) {
            $baseQuery->where('created_at', '>=', $from);
        }
        if ($to) {
            $baseQuery->where('created_at', '<=', $to);
        }

        $totalReferrals = (clone $baseQuery)->count();

        $withOrders = (clone $baseQuery)
            ->whereHas('tasks')
            ->count();

        $withCompleted = (clone $baseQuery)
            ->whereHas('completedTasks')
            ->count();

        // Конверсия
        $conversionToOrder = $totalReferrals > 0
            ? round($withOrders / $totalReferrals * 100, 2)
            : 0;

        $conversionToCompleted = $totalReferrals > 0
            ? round($withCompleted / $totalReferrals * 100, 2)
            : 0;

        // Динамика по дням (кол-во новых рефералов)
        $daily = (clone $baseQuery)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        // Топ партнёров по количеству приведённых
        $topReferrers = ReferralRelationship::query()
            ->selectRaw('referral_links.user_id as referrer_id, COUNT(*) as referrals_count')
            ->join('referral_links', 'referral_relationships.referral_link_id', '=', 'referral_links.id')
            ->groupBy('referral_links.user_id')
            ->orderByDesc('referrals_count')
            ->limit(10)
            ->get();

        return response()->json([
            'summary' => [
                'total_referrals'          => $totalReferrals,
                'referrals_with_orders'    => $withOrders,
                'referrals_with_completed' => $withCompleted,
                'conversion_to_order'      => $conversionToOrder,
                'conversion_to_completed'  => $conversionToCompleted,
            ],
            'daily' => $daily,
            'top_referrers' => $topReferrers,
        ]);
    }
}
