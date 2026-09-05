<?php

declare(strict_types=1);

namespace App\Services\Orders\Transitions;

use RuntimeException;

final class OrderTransitionException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function paidBypassForbidden(): self
    {
        return new self(
            'paid_bypass_forbidden',
            'PAID may only be written through the canonical confirmed-inbound path',
        );
    }

    public static function invalidPaidFrom(int $from): self
    {
        return new self(
            'invalid_paid_from_status',
            'PAID cannot be written from status '.$from,
        );
    }

    public static function transitionFailed(string $reason): self
    {
        return new self('paid_transition_failed', $reason);
    }

    public static function fundedRejectForbidden(int $from, int $to): self
    {
        return new self(
            'funded_reject_forbidden',
            sprintf(
                'Funded order (status %d) cannot be moved to %d. Use Needs review / Freeze, then reconcile with refund proof outside ordinary reject.',
                $from,
                $to
            ),
        );
    }

    public static function finalStatusLocked(int $from, int $to): self
    {
        return new self(
            'final_status_locked',
            sprintf('Completed order (status %d) cannot transition to %d.', $from, $to),
        );
    }
}
