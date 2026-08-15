<?php

declare(strict_types=1);

namespace App\Services\Orders\Transitions;

use App\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Confirmed inbound evidence → exactly one PAID write.
 * Independent of reconciliation (which only detects failures).
 */
final class ConfirmedInboundPaidTransition
{
    /**
     * @param  array{amount?:float|string, confirmations?:int, txid?:string, address?:?string}  $walletValues
     * @param  callable(int):void  $writePaid  receives from-status; must set PAID via permitted setStatus
     * @return array{outcome:string, from_status:int, to_status:int}
     */
    public function apply(int $taskId, array $walletValues, callable $writePaid): array
    {
        return DB::transaction(function () use ($taskId, $walletValues, $writePaid) {
            /** @var Task|null $task */
            $task = Task::query()->whereKey($taskId)->lockForUpdate()->first();
            if ($task === null) {
                throw OrderTransitionException::transitionFailed('task missing');
            }

            $txid = trim((string) ($walletValues['txid'] ?? ''));
            if ($txid !== '') {
                $other = (int) DB::table('wallet_transactions')
                    ->whereNull('deleted_at')
                    ->where('txid', $txid)
                    ->where('id_task', '!=', $taskId)
                    ->distinct()
                    ->count('id_task');
                if ($other > 0) {
                    PaymentTransitionMetrics::increment('payment_detected_transition_failed');
                    Log::error('payment_detected_transition_failed', [
                        'event' => 'duplicate_inbound_txid',
                        'task_id' => $taskId,
                    ]);
                    throw OrderTransitionException::transitionFailed('duplicate inbound txid');
                }

                WalletTransaction::updateOrCreate(
                    ['id_task' => $taskId],
                    [
                        'amount' => $walletValues['amount'] ?? null,
                        'confirmations' => (int) ($walletValues['confirmations'] ?? 1),
                        'txid' => $txid,
                        'address' => $walletValues['address'] ?? null,
                    ]
                );
            }

            $from = (int) $task->status;
            if ($from === TaskStatusEnum::PAID->value) {
                PaymentTransitionMetrics::increment('duplicate_payment_success_noop');
                Log::info('duplicate_payment_success_noop', [
                    'event' => 'already_paid',
                    'task_id' => $taskId,
                ]);

                return [
                    'outcome' => 'already_paid',
                    'from_status' => $from,
                    'to_status' => TaskStatusEnum::PAID->value,
                ];
            }

            if (!OrderTransitionService::isPaidFrom($from)) {
                PaymentTransitionMetrics::increment('payment_detected_transition_failed');
                PaymentTransitionMetrics::increment('confirmed_funds_wrong_status');
                Log::error('payment_detected_transition_failed', [
                    'event' => 'ineligible_status_for_paid',
                    'task_id' => $taskId,
                    'from_status' => $from,
                ]);
                throw OrderTransitionException::invalidPaidFrom($from);
            }

            $writePaid($from);
            $task->refresh();
            if ((int) $task->status !== TaskStatusEnum::PAID->value) {
                PaymentTransitionMetrics::increment('payment_detector_transition_errors');
                Log::error('payment_detector_transition_errors', [
                    'event' => 'paid_write_did_not_persist',
                    'task_id' => $taskId,
                    'status_after' => (int) $task->status,
                ]);
                throw OrderTransitionException::transitionFailed('PAID write did not persist');
            }

            Log::info('order_marked_paid', [
                'event' => 'order_marked_paid',
                'task_id' => $taskId,
                'from_status' => $from,
            ]);

            return [
                'outcome' => 'paid',
                'from_status' => $from,
                'to_status' => TaskStatusEnum::PAID->value,
            ];
        });
    }
}
