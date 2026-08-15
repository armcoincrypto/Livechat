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
}
