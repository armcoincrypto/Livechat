<?php

declare(strict_types=1);

namespace App\Services\Orders\Transitions;

use App\Enums\TaskStatusEnum;

/**
 * Canonical financial write policy for tasks.status mutations.
 *
 * PAID (7) and COMPLETED (4) still require explicit write permits.
 * Funded/final termination to REJECTED/CANCELED is blocked unless an
 * explicit funded-termination permit is granted (not used in Wave A —
 * funded problems must freeze/review instead).
 */
final class OrderTransitionService
{
    /**
     * Detector/callback/repair inbound statuses that may become PAID.
     *
     * PENDING_PAYMENT (2) and PROCESSING_PAYMENT (9) are required: confirmed
     * inbound evidence routinely arrives while the order is still awaiting
     * customer/operator advancement. Rejecting 2→7 caused invalid_paid_from_status
     * with funds already observed (Wave2 forensic: status2+wallet>0).
     */
    public const PAID_FROM = [
        TaskStatusEnum::PENDING_PAYMENT->value,
        TaskStatusEnum::WAITING_HANDLE->value,
        TaskStatusEnum::PROCESSING_PAYMENT->value,
        TaskStatusEnum::CHECK_PAYMENT->value,
        TaskStatusEnum::MERCHANT_CONFIRMATION->value,
    ];

    /** Statuses that mean inbound funds are confirmed / payout in flight. */
    public const FUNDED_STATUSES = [
        TaskStatusEnum::PAID->value,
        TaskStatusEnum::PAYOUT_QUEUE->value,
        TaskStatusEnum::PAYOUT_IN_PROGRESS->value,
    ];

    public const PERMIT_PAID = 'allow_paid_status_write';

    public const PERMIT_COMPLETE = 'allow_complete_status_write';

    /**
     * Explicit allow for exceptional funded → reject/cancel with external
     * reconciliation proof. Wave A does not grant this from admin/Telegram.
     */
    public const PERMIT_FUNDED_TERMINATION = 'allow_funded_termination_write';

    /**
     * @param  array<string,mixed>  $permits
     */
    public static function assertSetStatusPermitted(int $from, int $to, array $permits): void
    {
        if ($from === $to) {
            return;
        }

        if ($from === TaskStatusEnum::COMPLETED->value) {
            throw OrderTransitionException::finalStatusLocked($from, $to);
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
            if (! in_array($from, self::PAID_FROM, true)) {
                throw OrderTransitionException::invalidPaidFrom($from);
            }

            return;
        }

        if (self::isTerminationStatus($to)) {
            self::assertTerminationAllowed($from, $to, $permits);
        }
    }

    /**
     * Shared guard for reject()/cancelOrder paths before they call setStatus(5).
     *
     * @param  array<string,mixed>  $permits
     */
    public static function assertRejectAllowed(int $from, array $permits = []): void
    {
        self::assertTerminationAllowed($from, TaskStatusEnum::REJECTED->value, $permits);
    }

    /**
     * @param  array<string,mixed>  $permits
     */
    public static function assertTerminationAllowed(int $from, int $to, array $permits = []): void
    {
        if ($from === TaskStatusEnum::COMPLETED->value) {
            throw OrderTransitionException::finalStatusLocked($from, $to);
        }

        if (self::isFundedStatus($from) && empty($permits[self::PERMIT_FUNDED_TERMINATION])) {
            throw OrderTransitionException::fundedRejectForbidden($from, $to);
        }
    }

    public static function isPaidFrom(int $from): bool
    {
        return in_array($from, self::PAID_FROM, true);
    }

    public static function isFundedStatus(int $status): bool
    {
        return in_array($status, self::FUNDED_STATUSES, true);
    }

    public static function isTerminationStatus(int $status): bool
    {
        return in_array($status, [
            TaskStatusEnum::REJECTED->value,
            TaskStatusEnum::CANCELED_BY_USER->value,
        ], true);
    }

    /**
     * Payment-destination fail-closed park/restore/confirm.
     *
     * Allowed:
     * - PENDING_PAYMENT → REJECTED (pre-issue park / confirm reject)
     * - REJECTED → PENDING_PAYMENT only with fail-closed park marker (restore)
     */
    public static function assertFailClosedTransition(
        int $from,
        int $to,
        string $reason,
        \App\Models\Task $task
    ): void {
        if ($from === $to) {
            return;
        }

        if ($to === TaskStatusEnum::REJECTED->value) {
            if ($from !== TaskStatusEnum::PENDING_PAYMENT->value) {
                throw OrderTransitionException::failClosedInvalidFrom($from, $to, $reason);
            }
            // Not funded; ordinary termination permit path.
            self::assertTerminationAllowed($from, $to, []);

            return;
        }

        if ($to === TaskStatusEnum::PENDING_PAYMENT->value) {
            if ($from !== TaskStatusEnum::REJECTED->value) {
                throw OrderTransitionException::failClosedInvalidFrom($from, $to, $reason);
            }
            $opt = is_array($task->opt_params) ? $task->opt_params : [];
            $marker = $opt[\App\Services\Orders\Transitions\FailClosedPaymentDestinationTransition::OPT_KEY] ?? null;
            if (! is_array($marker)) {
                throw OrderTransitionException::failClosedRestoreWithoutPark($from);
            }

            return;
        }

        throw OrderTransitionException::failClosedInvalidFrom($from, $to, $reason);
    }
}
