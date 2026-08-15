<?php

namespace iEXPackages\Order\Concerns;

use App\Jobs\User\RegisterClientJob;
use App\Models\User;
use iEXPackages\ReferralSystem\Models\ReferralLink;
use iEXPackages\ReferralSystem\ReferralSystemFacade;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Faker\Factory as FakerFactory;

trait ManagesUserRegistration
{

    /**
     * Нормализует e-mail: trim + нижний регистр.
     */
    private function normalizeEmail(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    /**
     * Безопасно извлекает реф-код из cookie и нормализует его.
     * Разрешаем только буквы/цифры/дефис/подчёркивание, длина до 64.
     */
    private function normalizedReferralCode(): ?string
    {
        $raw = (string) request()->cookie('ref', '');
        $norm = preg_replace('/[^A-Za-z0-9_-]/u', '', trim($raw));
        $norm = $norm !== '' ? mb_substr($norm, 0, 64) : null;
        return $norm ?: null;
    }



    protected function resolveAuthenticatedUser(): void
    {
        if (!auth()->check() && !$this->options->has('email')) {
            return;
        }

        $email = $this->normalizeEmail($this->getClientEmail());
        if ($email === '') {
            return;
        }

        // Если в БД хранятся не в нижнем регистре, используем LOWER(email)
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();


        if ($user) {
            $this->authInfo = $user;
            $this->authId   = $user->id;
        }
    }


    protected function resolveUserAndVerifyEmail(): User
    {
        $isGuest = false;

        if (Auth::check()) {
            $user = $this->authInfo;
        } else {

            if ((int) iEXSetting('auto_register') === 1 || (int) $this->directionId->is_disable_auto_reg === 1) {
                $user = User::where('is_guest', 1)->first();
                $isGuest = true;
            } else {
                $user = $this->authInfo ?? $this->registerClient();

                // гостям рефералка не нужна
                if ((int) ($user->is_guest ?? 0) !== 1) {
                    $hasLink = ReferralLink::where('user_id', $user->id)->exists();

                    if (!$hasLink) {
                        ReferralSystemFacade::register($user, request()->cookie('ref'));
                    }
                }
            }
        }

        if ($this->shouldSendVerification($user, $isGuest)) {
            $user->sendEmailVerificationNotification();
        }

        return $user;
    }

    protected function shouldSendVerification(User $user, bool $isGuest): bool
    {
        return ($this->getInCurrency()?->is_email_verification_modal === 1)
            && is_null($user->email_verified_at)
            && !$isGuest;
    }

    protected function registerClient(): User
    {
        $password = Str::random(10);
        $user = $this->createNewUser($password);

        $this->assignUniqueUsername($user);
        $this->registerReferral($user);
        $this->sendRegistrationNotifications($user, $password);
        $this->authenticateNewUser($user);

        return $user;
    }

    protected function createNewUser(string $password): User
    {
        $email = $this->normalizeEmail($this->options['email'] ?? '');

        if ($email === '') {
            Log::error('Не удалось создать пользователя: отсутствует email');
            throw new \InvalidArgumentException('Email обязателен для создания пользователя.');
        }

        if (User::where('email', $email)->exists()) {
            Log::warning('Попытка создать пользователя с существующим email', ['email' => $email]);
            throw new \Exception('Пользователь с указанным email уже существует.');
        }

        try {
            $user = User::create([
                'name'       => sprintf('%s_%s', iEXContentLanguage('sitename'), Str::random(5)),
                'ip_address' => $this->clientIp(),
                'email'      => $email,
                'password'   => Hash::make($password),
                'language'   => app()->getLocale(),
            ]);

            Log::info('Создан новый пользователь', ['email' => $email, 'id' => $user->id]);

            return $user;
        } catch (\Throwable $e) {
            Log::error('Ошибка при создании пользователя', ['email' => $email, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    protected function assignUniqueUsername(User $user): void
    {
        $usernameTemplate = iEXContentLanguage('username_new_user');

        if (!empty($usernameTemplate)) {
            try {
                if ($usernameTemplate === ':randomUser:') {
                    $locale = app()->getLocale() === 'ru' ? 'ru_RU' : 'en_US';
                    $faker = FakerFactory::create($locale);

                    do {
                        $randomRealisticUserName = $faker->userName . '_' . Str::random(5);
                    } while (User::where('name', $randomRealisticUserName)->exists());

                    $uniqueName = $randomRealisticUserName;
                } else {
                    $uniqueName = str_replace(
                        [':id:', ':random:'],
                        [$user->id, Str::random(7)],
                        $usernameTemplate
                    );
                }

                $user->update(['name' => $uniqueName]);
            } catch (\Throwable $e) {
                Log::error('Ошибка назначения имени пользователю', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    protected function registerReferral(User $user): void
    {
        $referralCode = $this->normalizedReferralCode();
        if ($referralCode) {
            ReferralSystemFacade::register($user, $referralCode);
        }
    }

    protected function sendRegistrationNotifications(User $user, string $password): void
    {
        try {
            if ((bool)iEXSetting('is_email_auto_user')) {
                dispatch(new RegisterClientJob($user, $password))
                    ->delay(now()->addSeconds(5))
                    ->onQueue('low');
            }

            // Письмо верификации — только если почта ещё не подтверждена
            if (is_null($user->email_verified_at)) {
                $user->sendEmailVerificationNotification();
            }
        } catch (\Throwable $e) {
            Log::error('Ошибка отправки уведомления при регистрации: ' . $e->getMessage());
        }
    }

    protected function authenticateNewUser(User $user): void
    {
        if (Auth::guest()) {
            Auth::login($user, (bool)iEXSetting('is_remember_login'));
            Log::info('Пользователь автоматически авторизован после регистрации', ['id' => $user->id]);
        }
    }
}
