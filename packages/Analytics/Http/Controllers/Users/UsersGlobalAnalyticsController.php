<?php

namespace iEXPackages\Analytics\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use iEXPackages\Analytics\Services\Users\UsersGlobalAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsersGlobalAnalyticsController extends Controller
{
    public function __construct(
        protected UsersGlobalAnalyticsService $service
    ) {}

    /**
     * GET /admin/analytics/users/global
     *
     * Параметры:
     *  - from (Y-m-d) — не обязательно, по умолчанию 30 дней назад
     *  - to   (Y-m-d) — не обязательно, по умолчанию сегодня
     */
    public function index(Request $request): JsonResponse
    {
        $to = $request->query('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        $from = $request->query('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : (clone $to)->subDays(29)->startOfDay(); // 30 дней

        $summary       = $this->service->getSummary($from, $to);
        $changes       = $this->service->getChanges($from, $to);
        $newUsers      = $this->service->getNewUsersAnalytics($from, $to);
        $topUsers      = $this->service->getTopUsers($from, $to, 30);
        $activity      = $this->service->getActivitySeries($from, $to);
        $rfm           = $this->service->getRfmSegments($from, $to);
        $cohorts       = $this->service->getCohortsRetention($from, $to);
        $funnel        = $this->service->getFunnel($from, $to);
        $topExt        = $this->service->getTopUsersExtended($from, $to, 30);
        $usersDynamics = $this->service->getUsersDynamics($from, $to);
        $riskUsers     = $this->service->getRiskUsers($from, $to);
        $devices       = $this->service->getDeviceAnalytics($from, $to);

        $ltv           = $this->service->getLtvAnalytics($from, $to);
        $behavior      = $this->service->getBehaviorAnalytics($from, $to);
        $lifetime      = $this->service->getLifetimePaths($from, $to);
        $importance   =  $this->service->getImportanceAnalytics($from, $to);

        return response()->json([
            'period'    => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'summary'       => $summary,
            'changes'       => $changes,
            'new_users'     => $newUsers,
            'tops'          => $topUsers,
            'activity'      => $activity,
            'rfm'           => $rfm,
            'cohorts'       => $cohorts,
            'funnel'        => $funnel,
            'top_extended'  => $topExt,
            'users_dynamics'=> $usersDynamics,
            'risk_users'    => $riskUsers,
            'devices'       => $devices,
            'ltv'           => $ltv,
            'behavior'      => $behavior,
            'lifetime'      => $lifetime,
            'importance'    => $importance
        ]);
    }
}
