<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administrator\Vue;

use App\Http\Controllers\Controller;
use App\Support\WorkStatusFresh;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use iEXPackages\WorkStatus\Services\WorkStatusService;

class LayoutVueController extends Controller
{
    /**
     * options
     *
     * Возвращает данные для верхней части админки (layout/options).
     *
     * operatorStatus / техперерыв — work status only.
     * is_enabled_autopay_cron — separate setting; never mixed into pause/resume.
     */
    public function options(): JsonResponse
    {
        WorkStatusFresh::forgetRequestCache();
        $xmlPath = public_path('static/exports/currencies.xml');
        $items = is_file($xmlPath)
            ? substr_count((string) file_get_contents($xmlPath), '<item>')
            : 0;
        $hidden = \App\Services\Rates\RatesXmlMonitorGate::isHidden();
        $offline = WorkStatusFresh::isOffline();
        $feedState = 'ERROR';
        if ($offline || $hidden) {
            $feedState = ($items === 0) ? 'PAUSED' : 'ERROR';
        } elseif ($items > 0) {
            $feedState = 'LIVE';
        } else {
            $feedState = 'REBUILDING';
        }

        $ws = app(WorkStatusService::class)->getStatus();

        return response()->json([
            // true = обменник остановлен/оффлайн, false = работает
            'operatorStatus' => $offline,
            'bestchangeFeed' => [
                'state' => $feedState,
                'items' => $items,
                'hidden_flag' => $hidden,
                'xml_mtime' => is_file($xmlPath) ? date('c', (int) filemtime($xmlPath)) : null,
            ],
            'workStatus' => [
                'baseMode' => $ws->baseMode->value ?? (string) $ws->baseMode,
                'source' => $ws->source,
                'reason' => $ws->reason,
                'hasSchedules' => WorkStatusFresh::hasActiveSchedules(),
            ],
            'settings' => [
                // Auto-payouts only — independent from техперерыв.
                'is_enabled_autopay_cron' => (bool) iEXSetting('is_enabled_autopay_cron', 0),
            ],
        ]);
    }

    /**
     * Статус работы оператора (техперерыв ВКЛ/ВЫКЛ).
     *
     * Admin UI posts boolean status where true = offline/paused.
     * Legacy response body is the echoed status integer (UI ignores body on success).
     */
    public function postStatusOperator(Request $request)
    {
        WorkStatusFresh::forgetRequestCache();
        $oldOffline = WorkStatusFresh::isOffline();
        $wantOffline = (bool) $request->status;
        $exit = 0;

        if (! $wantOffline) {
            $exit = Artisan::call('operation:start');
        } else {
            $exit = Artisan::call('operation:stop');
        }

        Cache::forget('exchanger_client:tech_status_v1');
        WorkStatusFresh::forgetRequestCache();
        WorkStatusFresh::refreshRequestCache();

        $newOffline = WorkStatusFresh::isOffline();
        $xmlPath = public_path('static/exports/currencies.xml');
        $items = is_file($xmlPath)
            ? substr_count((string) file_get_contents($xmlPath), '<item>')
            : 0;
        $hidden = \App\Services\Rates\RatesXmlMonitorGate::isHidden();
        $ok = $exit === 0;
        if (! $wantOffline) {
            $ok = $ok && ! $newOffline && ! $hidden && $items > 0;
        } else {
            $ok = $ok && $newOffline && $items === 0;
        }

        Log::info('work_status_operator_toggle', [
            'admin_id' => Auth::id(),
            'old_offline' => $oldOffline ? 1 : 0,
            'new_offline' => $newOffline ? 1 : 0,
            'requested_offline' => $wantOffline ? 1 : 0,
            'artisan_exit' => $exit,
            'ok' => $ok ? 1 : 0,
            'feed_items' => $items,
            'hidden_flag' => $hidden ? 1 : 0,
            'autopay_untouched' => 1,
            'timestamp' => now()->toIso8601String(),
        ]);

        // Legacy admin UI expects a bare status scalar and only reverts on HTTP error.
        // Keep scalar primary body; attach diagnostics as headers for ops.
        return response()
            ->json($ok ? (int) $request->status : (int) $oldOffline)
            ->header('X-Exswaping-Toggle-Ok', $ok ? '1' : '0')
            ->header('X-Exswaping-Offline', $newOffline ? '1' : '0')
            ->header('X-Exswaping-Feed-Items', (string) $items);
    }

    /**
     * Обновление настроек (авто-выплаты only — never touches техперерыв).
     */
    public function postLayoutSettings(Request $request)
    {
        $user = Auth::user();
        if ($user === null) {
            return response()->json([
                'status' => 1,
                'message' => 'Unauthenticated',
            ], 401);
        }
        if (! $user->can('admin_autopayment')) {
            return response()->json([
                'status' => 1,
                'message' => 'Forbidden',
            ], 403);
        }

        iEXSetting([
            'is_enabled_autopay_cron' => $request->settings['is_enabled_autopay_cron'] ?? 0,
        ]);

        Log::info('layout_settings_autopay_only', [
            'admin_id' => Auth::id(),
            'is_enabled_autopay_cron' => (int) iEXSetting('is_enabled_autopay_cron', 0),
            'work_offline_unchanged' => WorkStatusFresh::isOffline() ? 1 : 0,
        ]);

        return response()->json([
            'status' => 0,
        ]);
    }
}
