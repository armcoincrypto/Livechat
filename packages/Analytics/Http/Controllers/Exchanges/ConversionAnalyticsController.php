<?php

declare(strict_types=1);

namespace iEXPackages\Analytics\Http\Controllers\Exchanges;

use App\Http\Controllers\Controller;
use iEXPackages\Analytics\Services\Exchanges\ConversionFunnelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Read-only conversion funnel for operators.
 * GET /admin/analytics/orders/conversion?period=7d&cohort=REAL_CUSTOMER
 */
class ConversionAnalyticsController extends Controller
{
    public function __construct(
        protected ConversionFunnelService $service
    ) {}

    public function funnel(Request $request): JsonResponse
    {
        $period = (string) $request->query('period', '7d');
        $cohort = $request->query('cohort');
        $cohort = is_string($cohort) ? $cohort : null;

        try {
            $data = $this->service->report($period, $cohort);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'allowed_periods' => array_keys(ConversionFunnelService::PERIOD_DAYS),
            ], 422);
        }

        return response()->json($data);
    }
}
