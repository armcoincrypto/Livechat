<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Support\Facades\iEXApp;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Jenssegers\Agent\Agent;

class SessionController extends Controller
{
    /**
     * Получаем список сессий
     *
     * @return JsonResponse
     */
    public function getSessions()
    {
        // Список активных сессий
        if((int)iEXSetting('is_visible_session_devices') == 1) {
            $sessions = collect(
                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', auth()->user()->getAuthIdentifier())
                    ->orderBy('last_activity', 'desc')
                    ->get()
            )->map(
                function ($session) {
                    $agent = tap(new Agent, fn($agent) => $agent->setUserAgent($session->user_agent));

                    return [
                        'agent'           => [
                            'platform' => $agent->platform(),
                            'browser'  => $agent->browser(),
                        ],
                        'ip'              => $session->ip_address,
                        'isCurrentDevice' => $session->id === request()->session()->getId(),
                        'isOnline'          =>  !empty($session->last_activity) and Carbon::parse($session->last_activity)->addMinutes(3) > Carbon::now(),
                        'lastActive'      => !empty($session->last_activity) ? Carbon::parse($session->last_activity)->diffForHumans()  : '',
                    ];
                }
            )->toArray();
        }

        return response()->json([
            'sessions' => $sessions ?? [],
        ]);
    }

    /**
     * Закрываем все авторизованные сеансы
     *
     * @param Request $request
     * @return JsonResponse
     * @throws AuthenticationException
     */
    public function logoutOtherDevices(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password_for_forget_sessions' => ['required', 'current_password:web']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->errors()->first(),
            ]);
        }

        Auth::logoutOtherDevices(security_xss($request->password_for_forget_sessions));
        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return response()->json([
            'status' => 0,
            'message' => 'Сессии аннулированы',
        ]);
    }

}
