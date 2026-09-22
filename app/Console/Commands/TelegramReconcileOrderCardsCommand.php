<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Services\TelegramOperator\TelegramOrderCardRegistry;
use Illuminate\Console\Command;

/**
 * Dry-run reconciliation of canonical Telegram operator cards for active orders.
 * Default is dry-run. --apply is refused until explicitly authorized in a later wave.
 */
final class TelegramReconcileOrderCardsCommand extends Command
{
    protected $signature = 'telegram:reconcile-order-cards
        {--dry-run : Default mode (explicit)}
        {--apply : Forbidden unless Wave2B authorizes live card create}
        {--limit=50 : Max active orders to inspect}
        {--days=7 : Lookback for actionable paid/waiting orders}';

    protected $description = 'Dry-run reconcile Telegram operator cards for active orders (no Telegram send by default).';

    public function handle(TelegramOrderCardRegistry $registry): int
    {
        if ($this->option('apply')) {
            $this->error('REFUSED: --apply is not authorized in Wave2. Re-run without --apply (dry-run only).');

            return self::FAILURE;
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        $days = max(1, min(90, (int) $this->option('days')));
        $since = now()->subDays($days);

        $actionableStatuses = [
            TaskStatusEnum::PENDING_PAYMENT->value,
            TaskStatusEnum::WAITING_HANDLE->value,
            TaskStatusEnum::CHECK_PAYMENT->value,
            TaskStatusEnum::MERCHANT_CONFIRMATION->value,
            TaskStatusEnum::PAID->value,
            TaskStatusEnum::PROCESSING_PAYMENT->value,
        ];

        $tasks = Task::query()
            ->whereIn('status', $actionableStatuses)
            ->where('updated_at', '>=', $since)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'status', 'updated_at', 'opt_params']);

        $wouldCreate = 0;
        $wouldEdit = 0;
        $wouldSkip = 0;
        $ambiguous = 0;
        $rows = [];

        foreach ($tasks as $task) {
            $cards = $registry->cards((int) $task->id);
            $status = (int) $task->status;
            if ($cards === []) {
                // Paid / waiting-handle without a card are the actionable create set.
                if (in_array($status, [
                    TaskStatusEnum::PAID->value,
                    TaskStatusEnum::WAITING_HANDLE->value,
                    TaskStatusEnum::CHECK_PAYMENT->value,
                ], true)) {
                    $wouldCreate++;
                    $rows[] = [
                        'task_id' => (int) $task->id,
                        'status' => $status,
                        'action' => 'WOULD_CREATE',
                    ];
                } else {
                    $wouldSkip++;
                    $rows[] = [
                        'task_id' => (int) $task->id,
                        'status' => $status,
                        'action' => 'WOULD_SKIP',
                    ];
                }

                continue;
            }

            if (count($cards) > 1) {
                $ambiguous++;
                $rows[] = [
                    'task_id' => (int) $task->id,
                    'status' => $status,
                    'action' => 'AMBIGUOUS',
                    'cards' => count($cards),
                ];

                continue;
            }

            $wouldEdit++;
            $rows[] = [
                'task_id' => (int) $task->id,
                'status' => $status,
                'action' => 'WOULD_EDIT',
                'message_id' => $cards[0]['message_id'] ?? null,
                'lifecycle' => $cards[0]['lifecycle'] ?? null,
            ];
        }

        $summary = [
            'mode' => 'dry-run',
            'lookback_days' => $days,
            'inspected' => $tasks->count(),
            'WOULD_CREATE' => $wouldCreate,
            'WOULD_EDIT' => $wouldEdit,
            'WOULD_SKIP' => $wouldSkip,
            'AMBIGUOUS' => $ambiguous,
            'rows' => $rows,
        ];

        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
