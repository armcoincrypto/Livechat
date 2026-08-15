<?php

declare(strict_types=1);

namespace iEXPackages\Analytics\Http\Controllers\Exchanges;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use iEXPackages\Analytics\Services\Exchanges\OrderExchangeTotalsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderExchangeTotalsController extends Controller
{
    public function __construct(
        protected OrderExchangeTotalsService $service
    ) {}


    public function topAndLowDays(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolvePeriod($request);
        $deviceType = $request->query('device_type');

        $data = $this->service->getTopAndLowDays($from, $to, $deviceType);

        return response()->json($data);
    }

    /**
     * Сводка по обороту за период (+ сравнение с предыдущим периодом).
     *
     * GET /admin/analytics/order-exchange-totals/summary?from=2025-11-01&to=2025-11-22
     */
    public function summary(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolvePeriod($request);
        $deviceType = $request->query('device_type'); // например: 'desktop', 'mobile', 'tablet'

        $data = $this->service->getSummary($from, $to, $deviceType);

        return response()->json($data);
    }

    /**
     * Динамика по дням: сумма, кол-во, средний чек.
     *
     * GET /admin/analytics/order-exchange-totals/daily?from=...&to=...
     */
    public function daily(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolvePeriod($request);
        $deviceType = $request->query('device_type'); // 'desktop' | 'mobile' | 'tablet' | null

        $data = $this->service->getDailyDynamics($from, $to, $deviceType);

        return response()->json($data);
    }

    /**
     * Статистика по менеджерам за период.
     *
     * GET /admin/analytics/order-exchange-totals/managers?from=...&to=...
     */
    public function managers(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolvePeriod($request);
        $deviceType = $request->query('device_type'); // например: 'desktop', 'mobile', 'tablet'

        $data = $this->service->getManagersStats($from, $to, $deviceType);

        return response()->json($data);
    }

    /**
     * Разбивка по дням с менеджерами внутри дня.
     *
     * GET /admin/analytics/order-exchange-totals/daily-with-managers?from=...&to=...
     */
    public function dailyWithManagers(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolvePeriod($request);

        $data = $this->service->getDailyWithManagers($from, $to);

        return response()->json($data);
    }

    /**
     * Разбор периода из запроса.
     *
     * ?from=YYYY-MM-DD&to=YYYY-MM-DD
     * если не передано — по умолчанию последние 30 дней.
     */
    protected function resolvePeriod(Request $request): array
    {
        $to = $request->query('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : now()->endOfDay();

        $from = $request->query('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : (clone $to)->subDays(29)->startOfDay(); // последние 30 дней

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }
}
