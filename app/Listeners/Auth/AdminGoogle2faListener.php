<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Models\User;
use iEXPackages\AuthAudit\Models\AuthEvent;
use App\Support\Facades\iEXApp;
use iEXPackages\AuthAudit\DTO\AuditData;
use iEXPackages\AuthAudit\Enums\AuditEvent;
use iEXPackages\AuthAudit\Enums\AuditResult;
use iEXPackages\AuthAudit\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use PragmaRX\Google2FALaravel\Events\LoginFailed;
use PragmaRX\Google2FALaravel\Events\LoginSucceeded;

/**
 * AdminGoogle2faListener — обработчик событий Google2FA (PragmaRX).
 *
 * Назначение:
 * - Фиксировать успешное/неуспешное подтверждение 2FA в новой системе аудита (AuthAudit).
 * - Отправлять Telegram уведомления.
 *
 * Примечание:
 * - Канал "admin" выставляем только если:
 *   1) путь находится в админке (iexexchanger.admin_folder)
 *   2) у пользователя есть permission allow_admin
 */
final class AdminGoogle2faListener
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Успешное подтверждение 2FA.
     */
    public function successful(LoginSucceeded $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;
        if (! $user) {
            return;
        }

        if ($this->shouldIgnoreAdminPrivateRequests()) {
            return;
        }

        // Уведомление администратору (Telegram)
        iEXApp::telegramNotificationForChannel('success_2fa', $user, __('Успешно прошел код авторизации'));

        // Audit: 2FA success
        $ipPrev = $this->resolveIpPrevFromAudit($user, (string) Request::ip());
        $this->audit->log($this->makeAudit(
            user: $user,
            event: AuditEvent::TwoFactorSuccess,
            result: AuditResult::Success,
            message: '2FA подтверждено',
            reasonCode: null,
            ipPrev: $ipPrev,
        ));
    }

    /**
     * Ошибка подтверждения 2FA.
     */
    public function failed(LoginFailed $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;
        if (! $user) {
            return;
        }

        if ($this->shouldIgnoreAdminPrivateRequests()) {
            return;
        }

        // Уведомление администратору (Telegram)
        iEXApp::telegramNotificationForChannel('failed_2fa', $user, __('Неправильно ввел код авторизации'));

        // Audit: 2FA failed
        $ipPrev = $this->resolveIpPrevFromAudit($user, (string) Request::ip());
        $this->audit->log($this->makeAudit(
            user: $user,
            event: AuditEvent::TwoFactorFailed,
            result: AuditResult::Fail,
            message: 'Ошибка 2FA',
            reasonCode: 'invalid_2fa_code',
            ipPrev: $ipPrev,
        ));
    }

    /**
     * Канал для 2FA событий.
     */
    private function resolveChannel(User $user): string
    {
        // "admin" только если это реально админ: путь админки + allow_admin
        if ($this->isAdminPath() && $user->can('allow_admin')) {
            return 'admin';
        }

        if (Request::is('api/*')) {
            return 'api';
        }

        return 'frontend';
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
     * Игнорируем технические запросы админки, чтобы не создавать шум в логах/уведомлениях.
     *
     * По твоему требованию исключаем только private-image/*.
     */
    private function shouldIgnoreAdminPrivateRequests(): bool
    {
        $adminFolder = (string) config('iexexchanger.admin_folder');

        return $adminFolder !== '' && Request::is("{$adminFolder}/private-image/*");
    }

    /**
     * Определяет предыдущий IP из новой системы аудита (auth_audit_events).
     *
     * Берём последний известный IP пользователя из AuthEvent.
     * Если он совпадает с текущим IP — возвращаем null, чтобы не шуметь.
     */
    private function resolveIpPrevFromAudit(User $user, string $currentIp): ?string
    {
        $prev = AuthEvent::query()
            ->where('user_id', (int) $user->id)
            ->whereNotNull('ip')
            ->orderByDesc('id')
            ->value('ip');

        if (!is_string($prev) || trim($prev) === '') {
            return null;
        }

        $prev = trim($prev);

        return $prev !== $currentIp ? $prev : null;
    }

    /**
     * Базовый meta-контекст для audit.
     *
     * @return array<string,mixed>
     */
    private function buildMeta(User $user): array
    {
        return [
            'is_admin_path' => $this->isAdminPath(),
            'is_admin_user' => $user->can('allow_admin'),
        ];
    }

    /**
     * Унифицированная сборка AuditData для 2FA событий.
     *
     * Здесь собраны все общие поля (user/email/guard/channel/ip/userAgent/meta),
     * чтобы не дублировать их в каждом обработчике.
     *
     * @param array<string,mixed> $metaExtra
     */
    private function makeAudit(
        User $user,
        AuditEvent $event,
        AuditResult $result,
        ?string $message,
        ?string $reasonCode,
        ?string $ipPrev,
        array $metaExtra = [],
    ): AuditData {
        return new AuditData(
            user: $user,
            email: $user->email ?? null,
            guard: Auth::getDefaultDriver(),
            channel: $this->resolveChannel($user),
            event: $event,
            result: $result,
            reasonCode: $reasonCode,
            message: $message,
            ip: Request::ip(),
            ipPrev: $ipPrev,
            userAgent: Request::userAgent(),
            meta: array_replace($this->buildMeta($user), $metaExtra),
        );
    }
}
