<?php
declare(strict_types=1);

namespace App\Http\Controllers\Administrator;

use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use PragmaRX\Google2FALaravel\Events\EmptyOneTimePasswordReceived;
use PragmaRX\Google2FALaravel\Events\LoginFailed;
use PragmaRX\Google2FALaravel\Events\LoginSucceeded;
use PragmaRX\Google2FALaravel\Google2FA;
use PragmaRX\Google2FALaravel\Support\Constants;

class Google2FaController
{
    use ThrottlesLogins;

    /**
     * Обработчик и проверка Google 2FA
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function verify(Request $request)
    {
        $password = $this->getOneTimePassword($request->get(config('google2fa.otp_input')));
        $isValid = (int)$this->verifyOneTimePassword($password, $request);

        // Если класс использует черту ThrottlesLogins, мы можем автоматически регулировать
        // попытки входа в систему для этого приложения. Мы зашифруем это по имени пользователя и
        // IP-адрес клиента, отправляющего эти запросы в это приложение.
        if (method_exists($this, 'hasTooManyLoginAttempts') && $this->hasTooManyLoginAttempts($request))
        {
            $this->fireLockoutEvent($request);

            $seconds = $this->limiter()->availableIn(
                $this->throttleKey($request)
            );

            return response()->json([
                'status' => 1,
                'message' => __('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ])
            ]);
        }

        $this->fireLoginEvent($isValid);

        if ($isValid) {
            (new Google2FA($request))->login();

            if (Constants::OTP_VALID == 'valid') {
                return response()->json([
                    'status' => 0
                ]);
            }
        }

        // Если попытка входа не удалась, мы увеличим количество попыток.
        // Для входа в систему и перенаправления пользователя обратно в форму входа. Конечно, когда это
        // пользователь превысит максимальное количество попыток, и его заблокируют.
        $this->incrementLoginAttempts($request);

        return response()->json([
            'status' => 1,
            'message' => __('Ключ введен неверно')
        ]);
    }

    /**
     * Проверка входа (Удачно/Неудачно)
     *
     * @param int $succeeded
     * @return int
     */
    private function fireLoginEvent(int $succeeded): int
    {
        event(
            $succeeded
                ? new LoginSucceeded(auth()->user())
                : new LoginFailed(auth()->user())
        );

        return $succeeded;
    }

    /**
     * Проверка введенного кода
     *
     * @param $password
     * @param Request $request
     * @return mixed
     */
    protected function verifyOneTimePassword($password, Request $request): mixed
    {
        return (new Google2FA($request))->verifyGoogle2FA(
            auth()->user()->{config('google2fa.otp_secret_column')},
            $this->getOneTimePassword($password)
        );
    }


    /**
     * Получаем пароль
     *
     * @param $password
     * @return string
     */
    protected function getOneTimePassword($password): string
    {
        if (empty($password)) {
            event(new EmptyOneTimePasswordReceived());
        }

        return $password;
    }

    /**
     * Макс. Кол-во попыток после которого активируется блокировка
     *
     * @return int
     */
    public function maxAttempts(): int
    {
        return 3;
    }

    /**
     * На сколько минут блокировок администратора, если были неудачные попытки
     *
     * @return int
     */
    public function decayMinutes(): int
    {
        return 5;
    }

    /**
     * Ключ проверок (НЕ МЕНЯТЬ)
     *
     * @param Request $request
     * @return string
     */
    protected function throttleKey(Request $request): string
    {
        return 'admin.2fa:'.$request->user()->id;
    }
}
