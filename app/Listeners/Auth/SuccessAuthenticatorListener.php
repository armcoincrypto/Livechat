<?php
declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Events\Auth\SuccessAuthenticatorEvent;
use App\Jobs\Notifications\EmailLoginSuccessfulJob;
use App\Support\Facades\iEXApp;
use Illuminate\Support\Facades\Session;

final class SuccessAuthenticatorListener
{
    /**
     * Действия после успешной аутентификации.
     *
     * Важно:
     * - Логи авторизаций/2FA ведутся через новую систему аудита (AuthAudit → auth_audit_events).
     * - Здесь оставляем только прикладные post-auth действия:
     *   1) Telegram уведомление (для allow_admin)
     *   2) Email о успешном входе
     *   3) Правило «одна сессия на пользователя»
     */
    public function handle(SuccessAuthenticatorEvent $event): void
    {
        if (!auth()->check()) {
            return;
        }

        $user = $event->user;
        if (!$user) {
            return;
        }

        if ($user->can('allow_admin')) {
            iEXApp::telegramNotificationForChannel('allowed_admin', $user);
        }

        // Email пользователю об успешном входе
        if ((int) iEXSetting('is_user_successful_login') === 1) {
            dispatch(
                new EmailLoginSuccessfulJob($user)
                    ->delay(now()->addMinutes(2))
                    ->onQueue('low')
            );
        }

        // Если разрешена одна сессия на пользователя
        if ((int) iEXSetting('app_one_session_user') === 1) {
            $lastSession = ($user->session_id !== null)
                ? Session::getHandler()->read($user->session_id)
                : null;

            if ($lastSession) {
                Session::getHandler()->destroy($user->session_id);
            }

            $user->session_id = Session::getId();
            $user->save();
        }
    }
}
