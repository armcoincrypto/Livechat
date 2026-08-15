<?php
declare(strict_types=1);

namespace iEXPackages\AuthAudit\Enums;

enum AuditEvent: string
{
    case LoginAttempt = 'login_attempt';
    case LoginSuccess = 'login_success';
    case LoginFailed  = 'login_failed';
    case Logout       = 'logout';
    case Lockout      = 'lockout';
    case PasswordReset = 'password_reset';

    case TwoFactorChallenge = 'two_factor_challenge';
    case TwoFactorSuccess   = 'two_factor_success';
    case TwoFactorFailed    = 'two_factor_failed';

    case IpChangedAdminLogout = 'ip_changed_admin_logout';
}
