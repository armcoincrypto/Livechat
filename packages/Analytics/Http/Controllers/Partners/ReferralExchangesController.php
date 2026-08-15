<?php

namespace iEXPackages\Analytics\Http\Controllers\Partners;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Bonuses\ReferralExchangesResources;
use iEXPackages\ReferralSystem\Models\ReferralLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReferralExchangesController extends Controller
{
    /**
     * Список партнёрских обменов с фильтрами
     */
    public function index(Request $request): JsonResponse
    {
        $query = ReferralLog::query()
            ->with(['user', 'user_admin', 'tasks'])
            ->filter($request->all());

        // Диапазон дат: поддерживаем from/to и date_from/date_to
        $from = $request->get('date_from', $request->get('from'));
        $to   = $request->get('date_to', $request->get('to'));

        if (! empty($from)) {
            $query->whereDate('created_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if (! empty($to)) {
            $query->whereDate('created_at', '<=', Carbon::parse($to)->endOfDay());
        }

        // Можно добавить фильтры по партнёру и рефералу
        if ($request->filled('partner_id')) {
            $query->where('id_user', $request->integer('partner_id'));
        }

        if ($request->filled('referral_id')) {
            $query->where('id_referral', $request->integer('referral_id'));
        }

        // Сортировка
        $sort = $request->get('sort', 'id');
        $direction = $request->get('direction', 'desc');

        $allowedSort = ['id', 'created_at', 'bonus', 'current_percent'];

        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'id';
        }

        $query->orderBy($sort, $direction === 'asc' ? 'asc' : 'desc');

        $logs = $query->paginate(20);

        return response()->json([
            'items' => new ReferralExchangesResources($logs),
        ]);
    }

    /**
     * Сводная аналитика партнёрских обменов
     */
    public function summary(Request $request): JsonResponse
    {
        $baseQuery = ReferralLog::query()
            ->filter($request->all());

        // Диапазон дат (from/to или date_from/date_to)
        $from = $request->get('date_from', $request->get('from'));
        $to   = $request->get('date_to', $request->get('to'));

        if (! empty($from)) {
            $baseQuery->whereDate('created_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if (! empty($to)) {
            $baseQuery->whereDate('created_at', '<=', Carbon::parse($to)->endOfDay());
        }

        // Общие цифры (используем числовое поле bonus_number)
        $totalExchanges = (clone $baseQuery)->count();
        $totalBonus     = (float) (clone $baseQuery)->sum('bonus_number');
        $avgBonus       = $totalExchanges > 0 ? round($totalBonus / $totalExchanges, 8) : 0.0;
        $maxBonus       = (float) (clone $baseQuery)->max('bonus_number');
        $minBonus       = (float) (clone $baseQuery)->min('bonus_number');

        // Динамика по дням: сколько логов и сумма бонусов
        $daily = (clone $baseQuery)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total, SUM(bonus_number) as bonus_sum')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        // Топ партнёров по сумме бонуса
        $topPartners = (clone $baseQuery)
            ->leftJoin('users', 'referral_log.id_user', '=', 'users.id')
            ->selectRaw('
                referral_log.id_user as partner_id,
                users.name as partner_name,
                users.email as partner_email,
                COUNT(*) as total_exchanges,
                SUM(referral_log.bonus_number) as bonus_sum
            ')
            ->groupBy('referral_log.id_user', 'users.name', 'users.email')
            ->orderByDesc('bonus_sum')
            ->limit(10)
            ->get();

        // Топ рефералов (кто принёс больше всего бонуса партнёрам)
        $topReferrals = (clone $baseQuery)
            ->leftJoin('users as referral_user', 'referral_log.id_referral', '=', 'referral_user.id')
            ->selectRaw('
                referral_log.id_referral as referral_id,
                referral_user.name as referral_name,
                referral_user.email as referral_email,
                COUNT(*) as total_exchanges,
                SUM(referral_log.bonus_number) as bonus_sum
            ')
            ->groupBy('referral_log.id_referral', 'referral_user.name', 'referral_user.email')
            ->orderByDesc('bonus_sum')
            ->limit(10)
            ->get();

        return response()->json([
            'summary' => [
                'total_exchanges' => $totalExchanges,
                'total_bonus'     => $totalBonus,
                'avg_bonus'       => $avgBonus,
                'min_bonus'       => $minBonus,
                'max_bonus'       => $maxBonus,
            ],
            'daily'         => $daily,
            'top_partners'  => $topPartners,
            'top_referrals' => $topReferrals,
        ]);
    }
}
