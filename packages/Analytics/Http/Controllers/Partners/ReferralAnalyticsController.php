<?php

namespace iEXPackages\Analytics\Http\Controllers\Partners;

use App\Http\Controllers\Controller;
use iEXPackages\Analytics\Services\Partners\ReferralAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralAnalyticsController extends Controller
{
    public function __construct(
        protected ReferralAnalyticsService $service
    ) {}

    /**
     * Общая аналитика по реферальной системе.
     *
     * GET /admin/bonuses/referrals/analytics
     * GET /admin/bonuses/referrals/analytics?from=YYYY-MM-DD&to=YYYY-MM-DD
     *
     * Возвращаемая структура JSON:
     *  - summary          — сводка по периодам (period + metrics)
     *  - detailed         — расширенная детализация по метрикам (counts, per_day, share_of_all, conversion)
     *  - detailed_period  — период, по которому построена детализация
     *  - top_transitions  — топ ссылок по переходам
     *  - top_partners     — топ партнёров по обменам и заработку
     *
     * Параметры запроса:
     *  - from (опционально) — дата начала периода в формате YYYY-MM-DD
     *  - to   (опционально) — дата окончания периода в формате YYYY-MM-DD
     *
     * Если from/to не указаны, используется глобальный период «за всё время».
     */
    public function index(Request $request): JsonResponse
    {
        $hasPeriod = $request->filled('from') || $request->filled('to');

        // Лимит топов можно настраивать через параметр, но с верхней границей.
        $topLimit = (int) min(50, max(1, (int) $request->query('top_limit', 10)));

        $summary         = null;
        $detailedPayload = null;
        $topTransitions  = [];
        $topPartners     = [];

        if ($hasPeriod) {
            // Явно заданный период: используем его во всех расчётах.
            [$from, $to] = $this->service->resolvePeriodFromRequest($request);

            $summary         = $this->service->getSummary($from, $to);
            $detailedPayload = $this->service->getPeriodDetails($from, $to);

            $topTransitions  = $this->service->getTopTransitionLinks($from, $to, $topLimit);
            $topPartners     = $this->service->getTopPartnersByExchanges($from, $to, $topLimit);
        } else {
            // Период не задан — строим глобальную аналитику «за всё время».
            if ($range = $this->service->resolveGlobalPeriodForSummary()) {
                [$from, $to]    = $range;
                $summary         = $this->service->getSummary($from, $to);
                $detailedPayload = $this->service->getPeriodDetails($from, $to);
            }

            // Топы за всё время без ограничения по датам
            $topTransitions  = $this->service->getTopTransitionLinks(null, null, $topLimit);
            $topPartners     = $this->service->getTopPartnersByExchanges(null, null, $topLimit);
        }

        // Аккуратно разбираем detailedPayload, чтобы не словить ошибку при отсутствии данных.
        $detailedMetrics = null;
        $detailedPeriod  = null;

        if (is_array($detailedPayload)) {
            $detailedMetrics = $detailedPayload['metrics'] ?? null;
            $detailedPeriod  = $detailedPayload['period'] ?? null;
        }

        return response()->json([
            'summary'          => $summary,
            'detailed'         => $detailedMetrics,
            'detailed_period'  => $detailedPeriod,
            'top_transitions'  => $topTransitions,
            'top_partners'     => $topPartners,
        ]);
    }
}
