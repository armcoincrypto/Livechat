<?php

declare(strict_types=1);

namespace App\Services\Orders\Reconciliation;

use RuntimeException;

final class FundedWrongStatusRepairException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $exitHint = 1,
    ) {
        parent::__construct($message);
    }

    public static function notAllowlisted(int $taskId): self
    {
        return new self('not_allowlisted', 'Task '.$taskId.' is not on the single-row repair allowlist.');
    }

    public static function evidenceFailed(string $reason): self
    {
        return new self('evidence_failed', $reason);
    }

    public static function unexpectedStatus(int $from): self
    {
        return new self('unexpected_status', 'Locked status '.$from.' is not 3 or 7.');
    }
}
