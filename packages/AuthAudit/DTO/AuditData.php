<?php
declare(strict_types=1);

namespace iEXPackages\AuthAudit\DTO;

use iEXPackages\AuthAudit\Enums\AuditEvent;
use iEXPackages\AuthAudit\Enums\AuditResult;

final class AuditData
{
    /**
     * @param  object|null  $user  Обычно App\Models\User, но тип не фиксируем для универсальности.
     * @param  array<string,mixed> $meta
     */
    public function __construct(
        public readonly ?object $user,
        public readonly ?string $email,
        public readonly ?string $guard,
        public readonly ?string $channel,
        public readonly AuditEvent $event,
        public readonly AuditResult $result,
        public readonly ?string $reasonCode = null,
        public readonly ?string $message = null,
        public readonly ?string $ip = null,
        public readonly ?string $ipPrev = null,
        public readonly ?string $userAgent = null,
        public readonly array $meta = [],
    ) {}
}
