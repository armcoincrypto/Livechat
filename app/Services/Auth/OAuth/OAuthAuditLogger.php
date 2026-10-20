<?php

declare(strict_types=1);

namespace App\Services\Auth\OAuth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use iEXPackages\AuthAudit\DTO\AuditData;
use iEXPackages\AuthAudit\Enums\AuditEvent;
use iEXPackages\AuthAudit\Enums\AuditResult;
use iEXPackages\AuthAudit\Services\AuditService;
use Throwable;

/**
 * Best-effort OAuth audit. Never logs tokens/codes/secrets.
 */
final class OAuthAuditLogger
{
    public function __construct(
        private readonly ?AuditService $audit = null,
    ) {
    }

    public function log(
        Request $request,
        string $event,
        string $result,
        ?User $user = null,
        ?string $reasonCode = null,
        array $meta = [],
    ): void {
        $safeMeta = array_intersect_key($meta, array_flip([
            'provider',
            'outcome',
            'intent',
        ]));

        try {
            if ($this->audit) {
                $auditEvent = match ($event) {
                    'oauth_login_success', 'oauth_register_success', 'oauth_link_success' => AuditEvent::LoginSuccess,
                    'oauth_login_failed', 'oauth_denied' => AuditEvent::LoginFailed,
                    default => AuditEvent::LoginAttempt,
                };
                $auditResult = $result === 'success' ? AuditResult::Success : AuditResult::Fail;

                $this->audit->log(new AuditData(
                    user: $user,
                    email: $user?->email,
                    guard: 'web',
                    channel: 'frontend',
                    event: $auditEvent,
                    result: $auditResult,
                    reasonCode: $reasonCode,
                    message: $event,
                    ip: $request->ip(),
                    userAgent: (string) $request->userAgent(),
                    meta: array_merge($safeMeta, ['oauth_event' => $event]),
                ));
            }
        } catch (Throwable $e) {
            Log::info('oauth.audit.skipped', ['event' => $event]);
        }
    }
}
