<?php
declare(strict_types=1);

namespace App\Http\Controllers\Administrator;

use App\Events\Auth\SuccessAuthenticatorEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SessionResource;
use App\Models\User;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use PragmaRX\Google2FALaravel\Support\Authenticator;
use Symfony\Component\HttpFoundation\IpUtils;

class AuthorizationController extends Controller
{
    use ThrottlesLogins;

    /**
     * Получаем данные авторизации
    */
    public function getSession()
    {
        return response()->json([
            'test' => 1
        ]);
    }

    /**
     * Авторизация пользователя в панели управления
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function loginUser(Request $request)
    {
        $validateUser = Validator::make($request->all(), $this->loginInputs());
        if ($validateUser->fails()) {
            return response()->json([
                'status' => 1,
                'message' => 'Неверные учетные данные',
            ]);
        }

        // Проверяем входные данные
        if (Auth::validate($request->only('email', 'password')))
        {
            $user = User::whereEmail(security_xss($request->email))->first();

            if ($this->hasTooManyLoginAttempts($request)) {
                $this->fireLockoutEvent($request);

                $seconds = $this->limiter()->availableIn(
                    $this->throttleKey($request)
                );

                return response()->json([
                    'status' => 1,
                    'message' => __('auth.throttle', ['seconds' => $seconds]),
                ]);
            }

            // Проверяем, не деактивирован ли аккаунт
            if ($user->deactivation == 1) {
                return response()->json([
                    'status' => 1,
                    'message' => 'Доступ запрещен',
                ]);
            }

            // Проверяем IP адреса из белого списка
            if (!empty($user->allowed_ip_addresses)) {
                $userAllowedIp = preg_replace('~[\r\n]+~', '', (string) $user->allowed_ip_addresses);
                $ipsToArray = array_values(array_filter(array_map(static function ($ip) {
                    return trim($ip);
                }, explode(',', $userAllowedIp)), static function ($ip) {
                    return $ip !== '';
                }));

                if (!empty($ipsToArray)) {
                    $isAllowed = IpUtils::checkIp($request->ip(), $ipsToArray);
                    if ($isAllowed === false) {
                        return response()->json([
                            'status' => 1,
                            'message' => 'Доступ запрещен',
                        ]);
                    }
                }
            }

            // Проверяем, не заблокирован ли аккаунт
            if ($user->isBanned()) {
                return response()->json([
                    'status' => 1,
                    'message' => 'Доступ запрещен',
                ]);
            }

            // Проверяем доступ администратора:
            // 1) у пользователя должна быть хотя бы одна выданная роль
            // 2) у пользователя должно быть право 'allow_admin'
            $hasAnyRole = $user->roles()->exists();
            $hasAllowAdmin = $user->can('allow_admin');

            if (!$hasAnyRole || !$hasAllowAdmin) {
                return response()->json([
                    'status'  => 1,
                    'message' => 'Доступ запрещен',
                ]);
            }

            // Проверяем все данные и авторизуем клиента
            $remember = (bool) $request->boolean('remember');
            if (Auth::attempt($request->only('email', 'password'), $remember))
            {
                $request->session()->regenerate();
                $this->clearLoginAttempts($request);

                if ($request->hasSession()) {
                    $request->session()->put('auth.password_confirmed_at', time());
                }

                return $this->sendLoginResponse($request);
            }
        }

        // Увеличиваем кол-во неудачных попыток входа в личный кабинет
        $this->incrementLoginAttempts($request);

        return response()->json([
            'status' => 1,
            'message' => 'Неверные учетные данные',
        ]);
    }

    /**
     * Send the response after the user was authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    protected function sendLoginResponse(Request $request)
    {
        $request->session()->regenerate();
        $this->clearLoginAttempts($request);

        if ($response = $this->authenticated($request, Auth::guard()->user())) {
            return $response;
        }

        return new JsonResponse([], 204);
    }


    /**
     * Событие после авторизации
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return JsonResponse
     */
    protected function authenticated(Request $request, User $user): JsonResponse
    {
        // Отсылаем событие об успешной авторизации клиента
        event(new SuccessAuthenticatorEvent($user));

        // Если у авторизованного клиента нет доступа к панели управления, то выкидываем
        if($user->cannot('allow_admin')) {
            $this->logout($request);
        }

        $user->is_frontend = 0;
        $user->save();

        $authenticator = app(Authenticator::class)->boot($request);

        return response()->json([
            'checkOTP' => (bool)$authenticator->isAuthenticated(),
            'isAuthenticated' => \auth()->check(),
            'user' => new SessionResource($user),
        ]);
    }

    /**
     * Log the user out of the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout(Request $request)
    {
        Auth::guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(
            '/'  . config('iexexchanger.admin_folder')
        );
    }

    /**
     * Get the maximum number of attempts to allow.
     *
     * @return int
     */
    public function maxAttempts()
    {
        return (int) iEXSetting('login_max_attempts', 5);
    }

    /**
     * Get the number of minutes to throttle for.
     *
     * @return int
     */
    public function decayMinutes()
    {
        return (int) iEXSetting('login_decay_minutes', 5);
    }

    /**
     * Get the throttle key for the given request.
     */
    protected function throttleKey(Request $request): string
    {
        $email = strtolower((string) $request->input('email', 'unknown'));
        return 'admin.auth:'.sha1($email.'|'.$request->ip());
    }

    /**
     * Входные параметры для авторизации
     *
     * @return array
    */
    protected function loginInputs(): array
    {
        $validate = [
            'email' => ['required', 'string', 'email', 'max:100'],
            'password' => ['required', 'string'],
        ];

        if (
            iEXSetting('is_security_captcha_type') == 1 &&
            iEXSetting('is_enabled_captcha_admin') == 1
        ) {
            $validate['recaptcha'] = 'required|captcha';
        }

        return $validate;
    }
}
