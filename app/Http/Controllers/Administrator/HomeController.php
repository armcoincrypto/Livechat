<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SessionResource;
use App\Support\Facades\iEXApp;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PragmaRX\Google2FALaravel\Support\Authenticator;

class HomeController extends Controller
{
    /**
     * Предварительная загрузка данных на FrontEnd
     *
     * @param Request $request
     * @return Factory|View|Application|\Illuminate\View\View
     */
    public function init(Request $request)
    {
        $frontAPI = collect([
            'statusSite' => work_is_offline(),
            'typeWorkingMode' => (int)iEXSetting('type_working_mode'),
            'hostname' => config('app.url'),
            'adminPath' => '/' . config('iexexchanger.admin_folder'),
            'siteUrl' => config('app.frontend_url'),
            'locales' => config('app.form_locales_filter'),
            'defaultLocale' => 'ru',
            'settings' => [
                'is_visible_session_devices' => (int)iEXSetting('is_visible_session_devices')
            ],
            'current_version' => config('iexexchanger.version.current'),
            'isReverb' => config('broadcasting.default') == 'reverb',
        ]);
        $frontAPI->put('captchaKey', (string)iEXSetting('env_spam_nocaptcha_sitekey'));
        $frontAPI->put('orderSettings', [
            'iex_order_live_auto_update_page' => (int)iEXSetting('iex_order_live_auto_update_page'),
            'iex_order_live_is_request_payment' => (int)iEXSetting('iex_order_live_is_request_payment'),
            'iex_order_live_auto_update_timeout' => (int)iEXSetting('iex_order_live_auto_update_timeout'),
            'iex_order_live_sound_notification' => (int)iEXSetting('iex_order_live_sound_notification'),
        ]);


        // Account (is_frontend=1) shares the session cookie. Do not tell the
        // admin SPA it is already signed in — AdminControlMiddleware will 400
        // and the UI hangs on a wait/Forbidden screen.
        if ($this->isAdminPanelSession()) {
            $authenticator = app(Authenticator::class)->boot($request);

            $frontAPI->put('user', new SessionResource(auth()->user()));
            $frontAPI->put('isAuthenticated', true);
            $frontAPI->put('checkOTP', (bool)$authenticator->isAuthenticated());
        } else {
            $frontAPI->put('isAuthenticated', false);
        }


        $frontAPI->put('isReadingMode', (int)config('iexexchanger.is_reading_mode'));


        return  view('admin-vue', [
            'frontAPI' => $frontAPI->toArray(),
        ]);
    }

    public function getInitialConfig(Request $request)
    {
        $frontAPI = collect([
            'hostname' => config('app.url'),
            'adminPath' => '/' . config('iexexchanger.admin_folder'),
            'locales' => config('app.form_locales_filter'),
            'defaultLocale' => 'ru',
            'settings' => [
                'is_visible_session_devices' => (int)iEXSetting('is_visible_session_devices')
            ],
            'current_version' => config('iexexchanger.version.current')
        ]);
        $frontAPI->put('captchaKey', (string)iEXSetting('env_spam_nocaptcha_sitekey'));
        $frontAPI->put('orderSettings', [
            'iex_order_live_auto_update_page' => (int)iEXSetting('iex_order_live_auto_update_page'),
            'iex_order_live_is_request_payment' => (int)iEXSetting('iex_order_live_is_request_payment'),
            'iex_order_live_auto_update_timeout' => (int)iEXSetting('iex_order_live_auto_update_timeout'),
            'iex_order_live_sound_notification' => (int)iEXSetting('iex_order_live_sound_notification'),
        ]);


        if ($this->isAdminPanelSession()) {
            $authenticator = app(Authenticator::class)->boot($request);

            $frontAPI->put('user', new SessionResource(auth()->user()));
            $frontAPI->put('isAuthenticated', true);
            $frontAPI->put('checkOTP', (bool)$authenticator->isAuthenticated());
        } else {
            $frontAPI->put('isAuthenticated', false);
        }


        $frontAPI->put('isReadingMode', (int)config('iexexchanger.is_reading_mode'));


        return \response()->json($frontAPI->toArray());
    }

    /**
     * Проверка соединения с панелью управления
     *
     * @return JsonResponse
     */
    public function getPing()
    {
        return \response()->json([
            'status' => 0
        ]);
    }

    /**
     * Работа с фотографиями
     *
     * @param Request $request
     * @return JsonResponse|\Illuminate\Http\Response
     */
    public function imageHandler(Request $request)
    {
        // Правила валидации запроса
        $validated = Validator::make($request->all(), [
            'filename' => ['required', 'regex:/^[\w,\s-]+\.[A-Za-z]{3,4}$/'],
            'path'     => ['required', 'regex:/^[\w\s\/-]+$/']
        ]);

        if ($validated->fails()) {
            return Response::json([
                'success' => false,
                'message' => $validated->errors()->first(),
            ], 422);
        }

        // Безопасно извлекаем параметры
        $filename = $validated->validated()['filename'];
        $path = $validated->validated()['path'];

        // Allowlist private folders only (no arbitrary disk reads).
        $allowedRoots = [
            'user_verification',
            'card_verification',
            'order_check',
            'order_chat',
            'payment_systems',
            'contests',
        ];
        $root = explode('/', trim($path, '/'))[0] ?? '';
        if ($root === '' || !in_array($root, $allowedRoots, true)) {
            return Response::json([
                'success' => false,
                'message' => 'Path is not allowed.',
            ], 403);
        }

        $fullPath = "{$path}/{$filename}";

        if (!Storage::disk('iexexchanger-disk')->exists($fullPath)) {
            return Response::json([
                'success' => false,
                'message' => 'Файл не найден.',
            ], 404);
        }

        $mime = (string) Storage::disk('iexexchanger-disk')->mimeType($fullPath);
        $lowerMime = strtolower($mime);
        // Never inline scriptable types.
        if (
            str_contains($lowerMime, 'svg')
            || str_contains($lowerMime, 'html')
            || str_contains($lowerMime, 'javascript')
            || str_contains($lowerMime, 'xml')
        ) {
            return Response::json([
                'success' => false,
                'message' => 'File type is not allowed.',
            ], 415);
        }

        $safeMime = in_array($lowerMime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'], true)
            ? $lowerMime
            : 'application/octet-stream';

        return Response::make(Storage::disk('iexexchanger-disk')->get($fullPath))
            ->header('Content-Type', $safeMime)
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Content-Disposition', 'inline; filename="'.basename($filename).'"');
    }

    /**
     * True only when the shared cookie is an admin-panel session.
     * Account logins set is_frontend=1 on the same user/cookie domain.
     */
    private function isAdminPanelSession(): bool
    {
        if (!\Auth::check()) {
            return false;
        }

        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        return (int) ($user->is_frontend ?? 0) !== 1;
    }
}
