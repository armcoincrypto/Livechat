<?php

declare(strict_types=1);

namespace App\Services\Orders\Reconciliation;

use App\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Services\Orders\Transitions\OrderTransitionService;
use Illuminate\Support\Facades\DB;

/**
 * Explicit single-row WAITING_HANDLE → PAID repair. Not a generic reconciler apply mode.
 * Does not complete, payout, or touch non-allowlisted tasks.
 */
final class FundedWrongStatusRepairer
{
    public const ACTOR = 'wave2.5_funded_wrong_status_repair';

    /**
     * Production allowlist: exactly one financial correction.
     *
     * @var array<int, array{public_id:string, from_status:int, to_status:int, txid:string}>
     */
    public const PRODUCTION_ALLOWLIST = [
        2250 => [
            'public_id' => '1769260564206',
            'from_status' => 3,
            'to_status' => 7,
            'txid' => '299dc9f2037d6aca4c43cd0f82ab02c2cb5bb9e659b6ffb5c0b6601d040f5730',
        ],
    ];

    /**
     * @param  array<int, array{public_id:string, from_status:int, to_status:int, txid:string}>  $allowlist
     */
    public function __construct(private readonly array $allowlist = self::PRODUCTION_ALLOWLIST)
    {
    }

    /**
     * @return array{outcome:string, task_id:int, from_status:int, to_status:int, status_log_3_to_7:int, repair:array<string,mixed>}
     */
    public function repair(int $taskId): array
    {
        $spec = $this->allowlist[$taskId] ?? null;
        if ($spec === null) {
            throw FundedWrongStatusRepairException::notAllowlisted($taskId);
        }

        return DB::transaction(function () use ($taskId, $spec) {
            /** @var Task|null $task */
            $task = Task::query()->whereKey($taskId)->lockForUpdate()->first();
            if ($task === null || $task->deleted_at !== null) {
                throw FundedWrongStatusRepairException::evidenceFailed('Task '.$taskId.' missing.');
            }

            $from = (int) $task->status;
            if ($from === TaskStatusEnum::PAID->value) {
                $repair = is_array($task->opt_params['funded_wrong_status_repair'] ?? null)
                    ? $task->opt_params['funded_wrong_status_repair']
                    : null;
                if ($repair === null) {
                    throw FundedWrongStatusRepairException::evidenceFailed('Already PAID without this repair audit.');
                }

                return [
                    'outcome' => 'already_repaired',
                    'task_id' => $taskId,
                    'from_status' => $from,
                    'to_status' => TaskStatusEnum::PAID->value,
                    'status_log_3_to_7' => $this->countThreeToSeven($taskId),
                    'repair' => $repair,
                ];
            }

            if ($from !== (int) $spec['from_status']) {
                throw FundedWrongStatusRepairException::unexpectedStatus($from);
            }

            $this->assertSafeToPromote($task, $spec);
            OrderTransitionService::assertSetStatusPermitted(
                (int) $task->status,
                (int) $spec['to_status'],
                [OrderTransitionService::PERMIT_PAID => true]
            );

            $audit = [
                'actor' => self::ACTOR,
                'reason' => 'CONFIRMED_FUNDS_WRONG_STATUS: wallet txid present, unique across orders, detector PAYMENT_SUCCESS without PAID write',
                'from_status' => $from,
                'to_status' => (int) $spec['to_status'],
                'txid' => $spec['txid'],
                'repaired_at' => now()->toIso8601String(),
            ];

            $opt = is_array($task->opt_params) ? $task->opt_params : [];
            $opt['funded_wrong_status_repair'] = $audit;

            $task->status = (int) $spec['to_status'];
            $task->started_at = now();
            $task->opt_params = $opt;
            $task->save();

            $logs = $this->countThreeToSeven($taskId);
            if ($logs !== 1) {
                throw FundedWrongStatusRepairException::evidenceFailed('Expected exactly one 3→7 status log, got '.$logs);
            }

            return [
                'outcome' => 'repaired',
                'task_id' => $taskId,
                'from_status' => $from,
                'to_status' => (int) $spec['to_status'],
                'status_log_3_to_7' => $logs,
                'repair' => $audit,
            ];
        });
    }

    /**
     * @param  array{public_id:string, from_status:int, to_status:int, txid:string}  $spec
     */
    private function assertSafeToPromote(Task $task, array $spec): void
    {
        if ((string) $task->public_id !== (string) $spec['public_id']) {
            throw FundedWrongStatusRepairException::evidenceFailed('public_id mismatch.');
        }
        if ($task->completed_at !== null) {
            throw FundedWrongStatusRepairException::evidenceFailed('completed_at is set; refuse.');
        }

        $opt = is_array($task->opt_params) ? $task->opt_params : [];
        if (!empty($opt['manual_settlement'])) {
            throw FundedWrongStatusRepairException::evidenceFailed('manual_settlement already present; refuse.');
        }

        $wallets = DB::table('wallet_transactions')
            ->where('id_task', $task->id)
            ->whereNull('deleted_at')
            ->where('txid', $spec['txid'])
            ->get();
        if ($wallets->isEmpty()) {
            throw FundedWrongStatusRepairException::evidenceFailed('Allowlisted txid not on this task.');
        }

        $otherTasks = (int) DB::table('wallet_transactions')
            ->whereNull('deleted_at')
            ->where('txid', $spec['txid'])
            ->where('id_task', '!=', $task->id)
            ->distinct()
            ->count('id_task');
        if ($otherTasks > 0) {
            throw FundedWrongStatusRepairException::evidenceFailed('Duplicate inbound txid attribution.');
        }

        foreach ([
            ['pays_transaction_data', 'id_task'],
            ['autosender_payment', 'id_order'],
        ] as [$table, $col]) {
            if (DB::table($table)->where($col, $task->id)->exists()) {
                throw FundedWrongStatusRepairException::evidenceFailed('Outbound marker in '.$table);
            }
        }
        if (DB::table('wallets_history')->where('id_task', $task->id)->whereNull('deleted_at')->exists()) {
            throw FundedWrongStatusRepairException::evidenceFailed('Outbound marker in wallets_history');
        }

        $row = [
            'status' => (int) $task->status,
            'has_wallet' => 1,
            'wallet_txid' => $spec['txid'],
            'has_merchant' => 1,
            'has_pays' => 0,
            'has_autosender' => 0,
            'has_wallets_history' => 0,
            'duplicate_inbound' => 0,
            'is_bot' => (int) $task->is_bot,
        ];
        if (FundedReconciliationClassifier::inboundStrength($row) !== FundedReconciliationClassifier::CONFIRMED_FUNDS) {
            throw FundedWrongStatusRepairException::evidenceFailed('Classifier strength is not CONFIRMED_FUNDS.');
        }
        if (FundedReconciliationClassifier::classification($row) !== 'FUNDED_WRONG_STATUS') {
            throw FundedWrongStatusRepairException::evidenceFailed('Classifier is not FUNDED_WRONG_STATUS.');
        }
        if ((int) $spec['to_status'] !== TaskStatusEnum::PAID->value) {
            throw FundedWrongStatusRepairException::evidenceFailed('Allowlist target is not PAID.');
        }
    }

    private function countThreeToSeven(int $taskId): int
    {
        return (int) DB::table('tasks_status_log')
            ->where('id_task', $taskId)
            ->where('old_status', 3)
            ->where('new_status', 7)
            ->whereNull('deleted_at')
            ->count();
    }
}
