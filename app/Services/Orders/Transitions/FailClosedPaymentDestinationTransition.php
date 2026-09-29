<?php

declare(strict_types=1);

namespace App\Services\Orders\Transitions;

use App\Enums\TaskStatusEnum;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Narrow payment-destination fail-closed status writer.
 *
 * Intentionally does NOT call Transaction::setStatus(): temporary park to
 * REJECTED must not reset reserves or enqueue OrderRecount (order 5088 class).
 * All writes still assert through OrderTransitionService and persist via Eloquent
 * so TaskObserver status logging / terminal cards remain consistent.
 */
final class FailClosedPaymentDestinationTransition
{
    public const OPT_KEY = 'payment_destination_fail_closed';

    public const REASON_PRE_ISSUE_PARK = 'pre_issue_not_payable';

    public const REASON_RESTORE_PENDING = 'destination_assigned_restore_pending';

    public const REASON_CONFIRM_REJECT = 'destination_missing_confirm_reject';

    /**
     * PENDING_PAYMENT → REJECTED before remote destination issue (timeout-safe).
     */
    public function parkNotPayable(Task $order): void
    {
        $this->apply($order, TaskStatusEnum::REJECTED, self::REASON_PRE_ISSUE_PARK, true);
    }

    /**
     * REJECTED → PENDING_PAYMENT after destination successfully assigned.
     */
    public function restorePending(Task $order): void
    {
        $this->apply($order, TaskStatusEnum::PENDING_PAYMENT, self::REASON_RESTORE_PENDING, false);
    }

    /**
     * Ensure REJECTED when destination is still missing (idempotent if already parked).
     */
    public function ensureRejected(Task $order): void
    {
        $this->apply($order, TaskStatusEnum::REJECTED, self::REASON_CONFIRM_REJECT, true);
    }

    private function apply(Task $order, TaskStatusEnum $to, string $reason, bool $parkMarker): void
    {
        DB::transaction(function () use ($order, $to, $reason, $parkMarker): void {
            /** @var Task|null $locked */
            $locked = Task::query()->whereKey($order->id)->lockForUpdate()->first();
            if ($locked === null) {
                throw OrderTransitionException::transitionFailed('fail_closed_task_missing');
            }

            $from = (int) $locked->status;
            $toValue = $to->value;
            if ($from === $toValue) {
                Log::info('order_transition_fail_closed', [
                    'task_id' => $locked->id,
                    'public_id' => $locked->public_id,
                    'from_status' => $from,
                    'to_status' => $toValue,
                    'reason' => $reason,
                    'transition_owner' => self::class,
                    'outcome' => 'noop_same_status',
                ]);

                return;
            }

            OrderTransitionService::assertFailClosedTransition($from, $toValue, $reason, $locked);

            $opt = is_array($locked->opt_params) ? $locked->opt_params : [];
            if ($parkMarker) {
                $opt[self::OPT_KEY] = [
                    'parked_at' => now()->toIso8601String(),
                    'parked_from' => $from,
                    'reason' => $reason,
                    'transition_owner' => self::class,
                ];
            } else {
                unset($opt[self::OPT_KEY]);
            }

            // Eloquent save (not query builder) so TaskObserver status log / cards fire.
            // Not Transaction::setStatus — avoids reserve reset / recount on temporary park.
            $locked->status = $toValue;
            $locked->opt_params = $opt;
            $locked->save();

            Log::info('order_transition_fail_closed', [
                'task_id' => $locked->id,
                'public_id' => $locked->public_id,
                'from_status' => $from,
                'to_status' => $toValue,
                'reason' => $reason,
                'transition_owner' => self::class,
                'outcome' => 'applied',
            ]);
        });

        $order->refresh();
    }
}
