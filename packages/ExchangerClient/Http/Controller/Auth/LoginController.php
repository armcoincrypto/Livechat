<?php

namespace iEXPackages\ExchangerClient\Http\Controller\Auth;

use App\Events\Auth\SuccessAuthenticatorEvent;
use App\Http\Controllers\Controller;
use App\Models\User;
use iEXPackages\ExchangerClient\Http\Resources\SessionResource;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;

class LoginController extends Controller
{
    use ThrottlesLogins;

    /**
     * Авторизация пользователя
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'error' => $validator->messages()->first(),
            ], 422);
        }

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            $seconds = $this->limiter()->availableIn($this->throttleKey($request));

            return response()->json([
                'status' => 1,
                'error' => __('Слишком много попыток входа. Попробуйте через :seconds секунд.', ['seconds' => $seconds]),
            ], 429);
        }

        $credentials = $request->only('email', 'password');

        $user = User::firstWhere('email', security_xss($request->email));

        if (!$user) {
            return response()->json([
                'status' => 1,
                'error' => __('Пользователь не найден.'),
            ], 404);
        }

        if ($user->deactivation == 1) {
            return response()->json([
                'status' => 1,
                'error' => __('Ваша учетная запись была деактивирована. Обратитесь в поддержку для получения дополнительной информации.'),
            ], 403);
        }

        if ($user->isBanned()) {
            return response()->json([
                'status' => 1,
                'error' => __('Ваша учетная запись заблокирована до :date. Обратитесь в поддержку для получения дополнительной информации.', ['date' => $user->banned_at])
            ], 403);
        }

        if (!empty($user->allowed_ip_addresses)) {
            $allowedIps = array_map('trim', explode(',', preg_replace('~[\r\n]+~', '', $user->allowed_ip_addresses)));

            if (!IpUtils::checkIp($request->ip(), $allowedIps)) {
                return response()->json([
                    'status' => 1,
                    'error' => __('Ваш IP-адрес не включён в список разрешённых.'),
                ], 403);
            }
        }

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            $this->clearLoginAttempts($request);

            $user->update(['is_frontend' => 1]);
            event(new SuccessAuthenticatorEvent($user));

            return response()->json([
                'status' => 0,
                'token' => $user->createToken('frontend')->plainTextToken,
                'user' => new SessionResource($user),
            ]);
        }

        $this->incrementLoginAttempts($request);

        return response()->json([
            'status' => 1,
            'error' => __('Неверный email или пароль.')
        ], 422);
    }

    /**
     * Правила валидации для авторизации
     */
    protected function rules(): array
    {
        $rules = [
            'email' => ['required', 'email', 'max:100', 'exists:users,email'],
            'password' => ['required'],
        ];

        if (
            iEXSetting('is_security_captcha_type') === 1 &&
            iEXSetting('is_enabled_captcha_login') === 1
        ) {
            $rules['recaptcha'] = ['required', 'captcha'];
        }

        return $rules;
    }

    public function username(): string
    {
        return 'email';
    }
}
