<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\Services;

use iEXPackages\ReferralSystem\Models\ReferralAuditLog;

class ReferralAuditLogger
{
    public function info(string $event, string $message, array $ctx = [], array $meta = []): void
    {
        $this->write('info', $event, $message, $ctx, $meta);
    }

    public function warning(string $event, string $message, array $ctx = [], array $meta = []): void
    {
        $this->write('warning', $event, $message, $ctx, $meta);
    }

    public function error(string $event, string $message, array $ctx = [], array $meta = []): void
    {
        $this->write('error', $event, $message, $ctx, $meta);
    }

    private function write(string $level, string $event, string $message, array $ctx, array $meta): void
    {
        ReferralAuditLog::create([
            'level' => $level,
            'event' => $event,
            'message' => mb_substr($message, 0, 500),

            'partner_user_id' => $ctx['partner_user_id'] ?? null,
            'client_user_id' => $ctx['client_user_id'] ?? null,
            'referral_link_id' => $ctx['referral_link_id'] ?? null,
            'referral_program_id' => $ctx['referral_program_id'] ?? null,
            'task_id' => $ctx['task_id'] ?? null,

            'meta' => $meta !== [] ? $meta : null,
            'trace_id' => $ctx['trace_id'] ?? null,
        ]);
    }
}
