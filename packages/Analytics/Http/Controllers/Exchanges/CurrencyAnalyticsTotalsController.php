<?php

namespace iEXPackages\Analytics\Http\Controllers\Exchanges;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use iEXPackages\Analytics\Services\Exchanges\CurrencyAnalyticsTotalsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyAnalyticsTotalsController extends Controller
{
    protected CurrencyAnalyticsTotalsService $service;

    public function __construct(CurrencyAnalyticsTotalsService $service)
    {
        $this->service = $service;
    }

    /**
     * Общая сумма обменов по системе на основе аналитики валют.
     *
     * Сейчас:
     *  - без кеширования;
     *  - 2 режима:
     *      mode = fixed   — используем *_usd поля в currencies_analytics;
     *      mode = current — считаем по текущему курсу через convert_to_usd();
     *
     * Возвращает:
     *  - totals: in / out / total в USD;
     *  - список валют с их in/out/total в USD.
     */
    public function index(Request $request): JsonResponse
    {
        $mode = $request->query('mode'); // 'fixed' | 'current' | null
        $totals = $this->service->getTotals($mode);

        return response()->json([
            'data' => [
                'totals'     => [
                    'mode'  => $totals['mode'],
                    'in'    => $totals['in'],
                    'out'   => $totals['out'],
                    'total' => $totals['total'],
                ],
                'currencies' => $totals['currencies'] ?? [],
            ],
        ]);
    }

    public function period(Request $request): JsonResponse
    {
        $from = Carbon::parse($request->query('from')); // YYYY-MM-DD
        $to   = Carbon::parse($request->query('to'));

        $totals = $this->service->getTotalsByPeriod($from, $to);

        return response()->json([
            'data' => [
                'totals'     => [
                    'mode'  => $totals['mode'],
                    'in'    => $totals['in'],
                    'out'   => $totals['out'],
                    'total' => $totals['total'],
                ],
                'currencies' => $totals['currencies'] ?? [],
            ],
        ]);
    }
}
