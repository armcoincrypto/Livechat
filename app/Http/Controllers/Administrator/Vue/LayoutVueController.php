<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administrator\Vue;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LayoutVueController extends Controller
{
    /**
     * options
     *
     * Возвращает данные для верхней части админки (layout/options).
     *
     * Важно:
     * - Статус оператора берём через единую точку входа (work_status/work_is_offline).
     *   Это гарантирует единый источник истины и исключает повторные вычисления статуса
     *   в рамках одного HTTP-запроса (если подключён WorkStatusMiddleware).
     *
     * @return JsonResponse
     */
    public function options(): JsonResponse
    {
        return response()->json([
            // true = обменник остановлен/оффлайн, false = работает
            'operatorStatus' => work_is_offline(),
            'settings' => [
                'is_enabled_autopay_cron' => (bool) iEXSetting('is_enabled_autopay_cron', 0),
            ],
        ]);
    }

    /**
     * Статус работы оператора
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function postStatusOperator(Request $request)
    {
        $oldOffline = work_is_offline();
        $wantOffline = (bool) $request->status;

        if (! $wantOffline) {
            Artisan::call('operation:start');
        } else {
            Artisan::call('operation:stop');
        }

        Cache::forget('exchanger_client:tech_status_v1');
        $newOffline = work_is_offline();

        Log::info('work_status_operator_toggle', [
            'admin_id' => Auth::id(),
            'old_offline' => $oldOffline ? 1 : 0,
            'new_offline' => $newOffline ? 1 : 0,
            'requested_offline' => $wantOffline ? 1 : 0,
            'timestamp' => now()->toIso8601String(),
        ]);

        return response()->json($request->status);
    }

    /**
     * Обновление настроек
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function postLayoutSettings(Request $request)
    {
        iEXSetting([
            'is_enabled_autopay_cron' => $request->settings['is_enabled_autopay_cron'] ?? 0
        ]);

        return response()->json([
            'status' => 0
        ]);
    }
}
