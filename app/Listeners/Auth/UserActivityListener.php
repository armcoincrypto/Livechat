<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Jobs\NewDeviceJob;
use App\Jobs\UserLockedOutJob;
use App\Models\User;
use App\Services\DeviceDetectorService;
use iEXPackages\AuthAudit\DTO\AuditData;
use iEXPackages\AuthAudit\Enums\AuditEvent;
use iEXPackages\AuthAudit\Enums\AuditResult;
use iEXPackages\AuthAudit\Services\AuditService;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use PragmaRX\Google2FALaravel\Facade as Google2Fa;

/**
 * UserActivityListener — центральный слушатель событий авторизации.
 *
 * Назначение:
 * - Пишет события авторизации в новую систему аудита (AuthAudit → auth_audit_events).
 * - Обновляет данные пользователя при успешном логине (last_login_at, ip_address, user_agent и т.д.).
 * - Выполняет контроль смены IP для админов (permission allow_admin) внутри админки.
 * - Отправляет уведомление о входе с нового устройства (на базе флага is_new_device из AuthAudit).
 *
 * Важно про дубликаты:
 * - Событие Login (Illuminate\Auth\Events\Login) срабатывает один раз при успешном входе.
 * - Событие Authenticated может срабатывать многократно (каждый запрос при активной сессии),
 *   поэтому здесь НЕЛЬЗЯ писать login_success.
 */
final class UserActivityListener
{
    public function __construct(
        private AuditService          $audit,
        private DeviceDetectorService $deviceDetector,
    ) {}

    /**
     * Попытка входа в систему (до проверки пароля).
     *
     * Здесь пользователь может быть неизвестен, поэтому логируем попытку
     * по email/login из credentials и определяем канал только по пути.
     */
    public function attempting(Attempting $event): void
    {
        $login = $this->extractLogin($event->credentials ?? []);

        $this->audit->log(new AuditData(
            user: null,
            email: $login,
            guard: Auth::getDefaultDriver(),
            channel: $this->resolveChannelForGuest(),
            event: AuditEvent::LoginAttempt,
            result: AuditResult::Unknown,
            reasonCode: null,
            message: null,
            ip: Request::ip(),
            ipPrev: null,
            userAgent: Request::userAgent(),
            meta: [
                'attempting' => true,
                'is_admin_path' => $this->isAdminPath(),
            ],
        ));
    }

    /**
     * Ошибка авторизации (неверные креды/пользователь).
     *
     * Пользователь может быть null — это нормальная ситуация (например, email не найден).
     */
    public function failed(Failed $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;
        $login = $this->extractLogin($event->credentials ?? []) ?? ($user?->email);

        $this->audit->log(new AuditData(
            user: $user,
            email: $login,
            guard: Auth::getDefaultDriver(),
            channel: $this->resolveChannel($user),
            event: AuditEvent::LoginFailed,
            result: AuditResult::Fail,
            reasonCode: 'invalid_credentials',
            message: 'Неудачная попытка входа',
            ip: Request::ip(),
            ipPrev: $user?->ip_address,
            userAgent: Request::userAgent(),
            meta: $this->buildMeta($user),
        ));
    }

    /**
     * Выход пользователя из аккаунта.
     */
    public function logout(Logout $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;
        if (! $user) {
            return;
        }

        // Обновляем служебные поля пользователя
        if (isset($user->last_logout_at)) {
            $user->last_logout_at = Carbon::now();
        }
        if (isset($user->is_frontend)) {
            $user->is_frontend = 0;
        }
        $user->save();

        // Пишем audit: logout
        $this->audit->log(new AuditData(
            user: $user,
            email: $user->email ?? null,
            guard: Auth::getDefaultDriver(),
            channel: $this->resolveChannel($user),
            event: AuditEvent::Logout,
            result: AuditResult::Success,
            reasonCode: null,
            message: 'Выход из аккаунта',
            ip: Request::ip(),
            ipPrev: null,
            userAgent: Request::userAgent(),
            meta: $this->buildMeta($user),
        ));
    }

    /**
     * Успешный вход (срабатывает один раз при логине).
     *
     * В этом обработчике:
     * 1) Обновляем user-поля (последний вход, IP, агент и т.п.).
     * 2) Пишем audit login_success.
     * 3) Если is_new_device=true и включена настройка — отправляем уведомление.
     */
    public function login(Login $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;
        if (! $user) {
            return;
        }

        // Технические запросы админки (frontend-api/private-image) не должны создавать шум
        if ($this->shouldIgnoreAdminPrivateRequests()) {
            return;
        }

        $ip = (string) Request::ip();
        $ua = (string) Request::userAgent();
        $now = Carbon::now();

        // ВАЖНО: старый IP нужно взять ДО обновления пользователя.
        $prevIp = is_string($user->ip_address ?? null) ? (string) $user->ip_address : null;
        $prevIp = ($prevIp !== null && trim($prevIp) !== '') ? $prevIp : null;

        // Инфо об устройстве
        $deviceInfo = $this->deviceDetector->getInfo();

        // 1) Обновляем данные пользователя.
        // logged_ip_address drives admin IP-change control — only stamp it on
        // admin-panel logins so Account (site) logins cannot poison it and
        // lock the operator out of /iexadmin with a Forbidden wait page.
        $fill = [
            'num_auth'          => (int) $user->num_auth + 1,
            'ip_address'        => $ip,
            'user_agent'        => $ua,
            'last_activity_at'  => $now,
            'last_login_at'     => $now,
            'language'          => app()->getLocale(),
            'user_browser'      => $deviceInfo['browser']     ?? null,
            'user_device'       => $deviceInfo['device_type'] ?? null,
        ];
        if ($this->isAdminPath()) {
            $fill['logged_ip_address'] = $ip;
        }
        $user->forceFill($fill)->save();

        // 2) Пишем audit login_success
        $authEvent = $this->audit->log(new AuditData(
            user: $user,
            email: $user->email ?? null,
            guard: Auth::getDefaultDriver(),
            channel: $this->resolveChannel($user),
            event: AuditEvent::LoginSuccess,
            result: AuditResult::Success,
            reasonCode: null,
            message: 'Успешный вход',
            ip: $ip,
            ipPrev: $prevIp,
            userAgent: $ua,
            meta: array_replace($this->buildMeta($user), [
                'current_page' => Request::path(),
            ]),
        ));

        // 3) Уведомление о новом устройстве (на базе is_new_device из AuthAudit)
        if ((bool) ($authEvent->is_new_device ?? false) && (int) iEXSetting('is_notify_new_device') === 1) {
            NewDeviceJob::dispatch($user, $authEvent)
                ->delay($now->copy()->addMinute())
                ->onQueue('low');
        }
    }

    /**
     * Активная сессия (может срабатывать много раз).
     *
     * Здесь НЕ фиксируем login_success.
     * Используем только для:
     * - обновления активности админов в админке
     * - контроля смены IP (ip_change_control)
     */
    public function authenticated(Authenticated $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;
        if (! $user) {
            return;
        }

        if ($this->shouldIgnoreAdminPrivateRequests()) {
            return;
        }

        // Real admin panel session only — Account (is_frontend=1) shares the
        // cookie but must not trigger admin IP lockdown / Forbidden pages.
        if (! $this->isAdminPanelSession($user)) {
            return;
        }

        $user->update([
            'last_activity_at' => Carbon::now(),
            'ip_address' => Request::ip(),
            'current_page' => Request::path(),
        ]);

        if ((int) iEXSetting('ip_change_control') !== 1) {
            return;
        }

        $currentIp = (string) Request::ip();
        $previousIp = (string) ($user->logged_ip_address ?? '');

        if ($previousIp === '' || $previousIp === $currentIp) {
            return;
        }

        // Residential / CGNAT churn (e.g. 87.241.145 → 87.241.148) must not
        // kick the operator out. Only treat a different /16 as a real IP change.
        if ($this->sameIpv4Slash16($previousIp, $currentIp)) {
            $user->forceFill(['logged_ip_address' => $currentIp])->save();

            return;
        }

        $this->audit->log(new AuditData(
            user: $user,
            email: $user->email ?? null,
            guard: Auth::getDefaultDriver(),
            channel: 'admin',
            event: AuditEvent::IpChangedAdminLogout,
            result: AuditResult::Blocked,
            reasonCode: 'ip_change_control',
            message: 'Смена IP, авторизация сброшена',
            ip: $currentIp,
            ipPrev: $previousIp,
            userAgent: Request::userAgent(),
            meta: [
                'admin_folder' => (string) config('iexexchanger.admin_folder'),
            ],
        ));

        Auth::logout();
        Google2Fa::logout();

        $request = request();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $loginUrl = '/'.trim((string) config('iexexchanger.admin_folder', 'iexadmin'), '/').'/login';

        // Never leave the operator on a dead 403 / white wait page — send them
        // back to admin login so they can sign in from the new IP.
        if ($request->expectsJson() || $request->ajax() || str_contains('/'.$request->path(), '/frontend-api/')) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'status' => 1,
                    'code' => 'IP_CHANGED',
                    'message' => 'Изменение IP-адреса. Авторизация сброшена.',
                    'isAuthenticated' => false,
                ], 401)
            );
        }

        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            redirect()->to($loginUrl)
        );
    }

    /**
     * Lockout: слишком много попыток входа.
     */
    public function lockout(Lockout $event): void
    {
        $email = is_string($event->request->email ?? null) ? trim((string) $event->request->email) : '';
        $email = $email !== '' ? $email : null;

        $this->audit->log(new AuditData(
            user: null,
            email: $email,
            guard: Auth::getDefaultDriver(),
            channel: $this->resolveChannelForGuest(),
            event: AuditEvent::Lockout,
            result: AuditResult::Blocked,
            reasonCode: 'rate_limited',
            message: 'Lockout: слишком много попыток входа',
            ip: (string) $event->request->ip(),
            ipPrev: null,
            userAgent: (string) $event->request->userAgent(),
            meta: [
                'is_admin_path' => $this->isAdminPath(),
            ],
        ));

        if ((int) iEXSetting('is_lockout_auth') === 1 && $email) {
            $user = User::where('email', $email)->first();
            if ($user) {
                dispatch(new UserLockedOutJob($user, $event->request->ip()))
                    ->delay(now()->addMinutes(5))
                    ->onQueue('low');
            }
        }
    }

    /**
     * Сброс пароля.
     */
    public function passwordReset(PasswordReset $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->audit->log(new AuditData(
            user: $user,
            email: $user?->email,
            guard: Auth::getDefaultDriver(),
            channel: $this->resolveChannel($user),
            event: AuditEvent::PasswordReset,
            result: AuditResult::Success,
            reasonCode: null,
            message: 'Сброс пароля',
            ip: Request::ip(),
            ipPrev: null,
            userAgent: Request::userAgent(),
            meta: $this->buildMeta($user),
        ));
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Канал для пользователя (user может быть null).
     */
    private function resolveChannel(?User $user): string
    {
        if ($this->isAdminUserArea($user)) {
            return 'admin';
        }

        if ($this->isApiPath()) {
            return 'api';
        }

        return 'frontend';
    }

    /**
     * Канал для гостя (когда user ещё неизвестен).
     */
    private function resolveChannelForGuest(): string
    {
        if ($this->isAdminPath()) {
            return 'admin';
        }

        if ($this->isApiPath()) {
            return 'api';
        }

        return 'frontend';
    }

    /**
     * Проверка API пути.
     */
    private function isApiPath(): bool
    {
        return Request::is('api/*');
    }

    /**
     * Админка по пути (например /iexadmin/*).
     */
    private function isAdminPath(): bool
    {
        $adminFolder = (string) config('iexexchanger.admin_folder');
        if ($adminFolder === '') {
            return false;
        }

        return Request::is($adminFolder) || Request::is("{$adminFolder}/*");
    }

    /**
     * Реальная админ-зона: путь админки + permission allow_admin.
     */
    private function isAdminUserArea(?User $user): bool
    {
        return $this->isAdminPath() && $user !== null && $user->can('allow_admin');
    }

    /**
     * Admin panel session (not Account/site frontend session).
     */
    private function isAdminPanelSession(?User $user): bool
    {
        if (! $this->isAdminUserArea($user)) {
            return false;
        }

        return (int) ($user->is_frontend ?? 0) !== 1;
    }

    /**
     * True when both values are IPv4 addresses in the same /16 network.
     */
    private function sameIpv4Slash16(string $a, string $b): bool
    {
        if (! filter_var($a, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || ! filter_var($b, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        ) {
            return false;
        }

        $aParts = explode('.', $a);
        $bParts = explode('.', $b);

        return ($aParts[0] ?? null) === ($bParts[0] ?? null)
            && ($aParts[1] ?? null) === ($bParts[1] ?? null);
    }

    /**
     * Игнорируем технические запросы админки (private-image),
     * чтобы не создавать шум в аудит-логах.
     */
    private function shouldIgnoreAdminPrivateRequests(): bool
    {
        // Старый технический путь: iexadmin/private-image/*
        $adminFolder = (string) config('iexexchanger.admin_folder');

        if ($adminFolder !== '' && Request::is("{$adminFolder}/private-image/*")) {
            return true;
        }

        return false;
    }

    /**
     * Базовый meta-контекст для audit.
     *
     * @return array<string,mixed>
     */
    private function buildMeta(?User $user): array
    {
        return [
            'is_admin_path' => $this->isAdminPath(),
            'is_admin_user' => $user ? $user->can('allow_admin') : false,
        ];
    }

    /**
     * Достаём login/email из credentials.
     *
     * @param array<string,mixed> $credentials
     */
    private function extractLogin(array $credentials): ?string
    {
        $val = $credentials['email'] ?? $credentials['login'] ?? null;
        if (! is_string($val)) {
            return null;
        }

        $val = trim($val);
        return $val !== '' ? $val : null;
    }
}
