<?php
declare(strict_types=1);

namespace iEXPackages\AuthAudit\Enums;

enum AuditResult: string
{
    case Success = 'success';
    case Fail = 'fail';
    case Blocked = 'blocked';
    case Challenge = 'challenge';
    case Unknown = 'unknown';
}
