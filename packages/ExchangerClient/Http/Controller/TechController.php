<?php
namespace iEXPackages\ExchangerClient\Http\Controller;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class TechController
{
    /**
     * Получаем статус технических работ
     *
     * Short-cache so admin WorkStatus flips propagate quickly while protecting
     * PHP-FPM under admin/bot polling storms. Intentional pause (on=1) must not
     * be confused with transport failure — callers still treat missing JSON as
     * unavailable.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $payload = Cache::remember('exchanger_client:tech_status_v1', 3, static function (): array {
            return [
                'technicalMode' => [
                    'on' => isJobOffline() ? 1 : 0,
                ],
            ];
        });

        return response()->json($payload);
    }
}
