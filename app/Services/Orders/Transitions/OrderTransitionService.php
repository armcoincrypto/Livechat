<?php

declare(strict_types=1);

namespace App\Services\Orders\Transitions;

use App\Enums\TaskStatusEnum;

/**
 * Smallest-safe financial write policy. Does not replace all setStatus callers.
 *
 * PAID (7) and COMPLETED (4) require explicit permits on the writer.
 * Other statuses remain caller-compatible.
 */
final class OrderTransitionService
{
    /** Detector/callback/repair inbound statuses that may become PAID. */
    public const PAID_FROM = [
        TaskStatusEnum::WAITING_HANDLE->value,
        TaskStatusEnum::CHECK_PAYMENT->value,
        TaskStatusEnum::MERCHANT_CONFIRMATION->value,
    ];

    public const PERMIT_PAID = 'allow_paid_status_write';

    public const PERMIT_COMPLETE = 'allow_complete_status_write';

    /**
     * @param  array<string,mixed>  $permits
     */
    public static function assertSetStatusPermitted(int $from, int $to, array $permits): void
    {
        if ($from === $to) {
            return;
        }

        if ($to === TaskStatusEnum::COMPLETED->value) {
            if (empty($permits[self::PERMIT_COMPLETE])) {
                throw \App\Services\Orders\ManualCompletion\ManualCompletionException::bypassForbidden();
            }

            return;
        }

        if ($to === TaskStatusEnum::PAID->value) {
            if (empty($permits[self::PERMIT_PAID])) {
                throw OrderTransitionException::paidBypassForbidden();
            }
            if (!in_array($from, self::PAID_FROM, true)) {
                throw OrderTransitionException::invalidPaidFrom($from);
            }
        }
    }

    public static function isPaidFrom(int $from): bool
    {
        return in_array($from, self::PAID_FROM, true);
    }
}
