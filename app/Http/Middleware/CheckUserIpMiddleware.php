<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Facades\iEXApp;
use Closure;
use iEXPackages\SmartMailer\Facades\SmartMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PragmaRX\Google2FALaravel\Facade as Google2Fa;

use iEXPackages\AuthAudit\DTO\AuditData;
use iEXPackages\AuthAudit\Enums\AuditEvent;
use iEXPackages\AuthAudit\Enums\AuditResult;
use iEXPackages\AuthAudit\Models\AuthEvent;
use iEXPackages\AuthAudit\Services\AuditService;
use Illuminate\Support\Facades\Auth;

class CheckUserIpMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ((int)iEXSetting('ip_change_control') != 2) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && $user->can('allow_admin')) {
            $currentIp = $request->ip();
            // Get last recorded IP from new audit system
            $lastIp = AuthEvent::query()
                ->where('user_id', $user->id)
                ->whereNotNull('ip')
                ->orderByDesc('id')
                ->value('ip');

            if (!$lastIp || $lastIp !== $currentIp) {
                // Log audit event
                $audit = app(AuditService::class);
                $audit->log(new AuditData(
                    user: $user,
                    email: $user->email,
                    guard: Auth::getDefaultDriver(),
                    channel: 'admin',
                    event: AuditEvent::IpChangedAdminLogout,
                    result: AuditResult::Blocked,
                    reasonCode: 'ip_change_control',
                    message: 'Смена IP, авторизация сброшена',
                    ip: $currentIp,
                    ipPrev: $lastIp ?: null,
                    userAgent: $request->userAgent(),
                    meta: [
                        'admin_folder' => (string) config('iexexchanger.admin_folder'),
                        'current_page' => $request->path(),
                    ]
                ));

                // Telegram notification
                iEXApp::telegramNotificationForChannel('admin_ip_change', $user);

                // Logout
                Auth::logout();
                Google2Fa::logout();

                // SmartMailer notification if the previous IP exists and differs
                if ($lastIp && $lastIp !== $currentIp) {
                    try {
                        SmartMailer::dispatch(
                            sendable: 'user_ip_changed',
                            model: $user,
                            email: $user->email,
                            delaySeconds: 10,
                            queue: 'low',
                            data: ['newIp' => $currentIp, 'oldIp' => $lastIp]
                        );
                    } catch (\Throwable $e) {
                        Log::error('Ошибка отправки уведомления об изменении IP:', [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }

        return $next($request);
    }
}
