<?php

namespace iEXPackages\Analytics\Http\Controllers\Exchanges;

use App\Http\Controllers\Controller;
use iEXPackages\Analytics\Services\Exchanges\DirectionsAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DirectionsAnalyticsController extends Controller
{
    public function __construct(
        protected DirectionsAnalyticsService $service
    ) {}

    public function index(Request $request)
    {
        $rawFrom = $request->get('date_from', $request->get('from'));
        $rawTo   = $request->get('date_to', $request->get('to'));

        $from = $rawFrom
            ? Carbon::parse($rawFrom)->startOfDay()
            : null;

        $to = $rawTo
            ? Carbon::parse($rawTo)->endOfDay()
            : null;

        $stats = $this->service->getDirectionsStats($from, $to);

        return response()->json($stats);
    }
    public function all(Request $request)
    {
        $rawFrom = $request->get('date_from', $request->get('from'));
        $rawTo   = $request->get('date_to', $request->get('to'));

        $from = $rawFrom
            ? Carbon::parse($rawFrom)->startOfDay()
            : null;

        $to = $rawTo
            ? Carbon::parse($rawTo)->endOfDay()
            : null;

        $perPage = (int) $request->get('per_page', 30);
        if ($perPage <= 0) {
            $perPage = 30;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $search = $request->get('search');

        $paginator = $this->service->getAllDirectionsPaginated($from, $to, $perPage, $search);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from'        => $paginator->firstItem(),
                'last_page'   => $paginator->lastPage(),
                'per_page'    => $paginator->perPage(),
                'to'          => $paginator->lastItem(),
                'total'       => $paginator->total(),
            ],
        ]);
    }

    public function noDemand(Request $request)
    {
        $rawFrom = $request->get('date_from', $request->get('from'));
        $rawTo   = $request->get('date_to', $request->get('to'));

        $from = $rawFrom
            ? Carbon::parse($rawFrom)->startOfDay()
            : null;

        $to = $rawTo
            ? Carbon::parse($rawTo)->endOfDay()
            : null;

        $perPage = (int) $request->get('per_page', 30);
        if ($perPage <= 0) {
            $perPage = 30;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $search = $request->get('search');

        $paginator = $this->service->getNoDemandDirectionsPaginated($from, $to, $perPage, $search);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from'        => $paginator->firstItem(),
                'last_page'   => $paginator->lastPage(),
                'per_page'    => $paginator->perPage(),
                'to'          => $paginator->lastItem(),
                'total'       => $paginator->total(),
            ],
        ]);
    }

    /**
     * Топ-10 направлений за всё время / месяц / неделю.
     */
    public function top(Request $request)
    {
        $data = $this->service->getTopDirectionsSummary();

        return response()->json($data);
    }
}
