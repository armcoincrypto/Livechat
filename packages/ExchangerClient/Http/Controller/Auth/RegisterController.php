<?php

namespace iEXPackages\ExchangerClient\Http\Controller\Auth;

use App\Models\User;
use App\Rules\DomainEmailValidator;
use iEXPackages\ExchangerClient\Http\Resources\SessionResource;
use iEXPackages\ReferralSystem\ReferralSystemFacade;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Throwable;

class RegisterController
{
    /**
     * Регистрация нового клиента
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->validationRules(), $this->validationMessages());

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'error' => $validator->messages()->first(),
            ], 422);
        }

        $ban = ban_check_email(security_xss($request->email));

        if (is_array($ban)) {
            return response()->json([
                'status' => 1,
                'error' => __(
                    'Данный e-mail запрещён администратором. Причина: :reason',
                    [
                        'reason' => $ban['description']
                            ?? __('не указана'),
                    ]
                ),
                // опционально, если захочешь использовать на фронте
                'meta' => [
                    'ban_type'   => $ban['type'] ?? null,
                    'matched_by'=> $ban['matched_by'] ?? null,
                    'matched'   => $ban['matched'] ?? null,
                    'code'      => $ban['reason_code'] ?? null,
                ],
            ], 422);
        }

        try {
            $user = User::create([
                'name'          => strip_tags(security_xss($request->name)),
                'email'         => security_xss($request->email),
                'password'      => Hash::make(security_xss($request->password)),
                'ip_address'    => $request->ip(),
                'last_login'    => Carbon::now(),
                'last_activity' => Carbon::now(),
                'language'      => app()->getLocale(),
            ]);

            $ref = request()->cookie('ref');
            ReferralSystemFacade::register($user, $ref);

            event(new Registered($user));
            Auth::guard()->login($user);

            return response()->json([
                'status' => 0,
                'user'   => new SessionResource($user),
            ]);

        } catch (Throwable $exception) {
            return response()->json([
                'status' => 1,
                'error'  => __('Ошибка регистрации: :message', ['message' => $exception->getMessage()]),
            ], 500);
        }
    }

    /**
     * Правила валидации
     *
     * @return array
     */
    protected function validationRules(): array
    {
        $rules = [
            'name'     => ['required', 'string', 'max:50', 'regex:/^[A-Za-zА-Яа-яЁё\s\-]+$/u'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users', new DomainEmailValidator],
            'password' => ['required', 'string', 'min:6'],
        ];

        if (
            iEXSetting('is_security_captcha_type') === 1 &&
            iEXSetting('is_enabled_captcha_register') === 1
        ) {
            $rules['recaptcha'] = ['required', 'captcha'];
        }

        return $rules;
    }

    /**
     * Сообщения валидации
     *
     * @return array
     */
    protected function validationMessages(): array
    {
        return [
            'name.required'      => __('Введите имя.'),
            'name.regex'         => __('Имя может содержать только буквы, пробелы и дефисы.'),
            'email.required'     => __('Введите email.'),
            'email.email'        => __('Введите корректный email адрес.'),
            'email.unique'       => __('Этот email уже зарегистрирован.'),
            'password.required'  => __('Введите пароль.'),
            'password.min'       => __('Пароль должен содержать минимум 6 символов.'),
            'recaptcha.required' => __('Подтвердите, что вы не робот.'),
            'recaptcha.captcha'  => __('Ошибка проверки CAPTCHA.'),
        ];
    }
}
