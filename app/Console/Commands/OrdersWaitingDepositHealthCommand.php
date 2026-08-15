<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Services\Orders\InboundPaymentDestinationGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Read-only: waiting orders that cannot show a deposit destination.
 * Does not allocate wallets or mutate tasks.
 */
final class OrdersWaitingDepositHealthCommand extends Command
{
    protected $signature = 'orders:waiting-deposit-health
        {--days=14 : Lookback window}
        {--limit=300 : Max waiting rows to inspect}
        {--format=json : json|table}';

    protected $description = 'Count waiting orders missing an assigned inbound payment destination.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $limit = max(1, (int) $this->option('limit'));

        $waiting = Task::query()
            ->where('status', TaskStatusEnum::PENDING_PAYMENT->value)
            ->where('created_at', '>=', now()->subDays($days))
            ->with(['direction_exchange.currency1'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $missingAssigned = 0;
        $missingAndNoSource = 0;
        $blockedPublicIds = [];

        foreach ($waiting as $task) {
            if (InboundPaymentDestinationGuard::taskHasAssignedDestination($task)) {
                continue;
            }
            $missingAssigned++;
            $dir = $task->direction_exchange;
            $noSource = $dir === null || ! InboundPaymentDestinationGuard::directionHasSource($dir);
            if ($noSource) {
                $missingAndNoSource++;
                $blockedPublicIds[] = (string) $task->public_id;
            }
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'metric' => 'waiting_crypto_order_without_deposit_destination',
            'waiting_sampled' => $waiting->count(),
            'missing_assigned_destination' => $missingAssigned,
            'missing_and_no_source' => $missingAndNoSource,
            'ok' => $missingAndNoSource === 0,
            'blocked_public_ids' => $blockedPublicIds,
        ];

        Log::info('orders:waiting-deposit-health', [
            'ok' => $payload['ok'],
            'missing_and_no_source' => $missingAndNoSource,
            'missing_assigned_destination' => $missingAssigned,
        ]);

        if ($this->option('format') === 'json') {
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['metric', 'value'],
                [
                    ['waiting_sampled', $payload['waiting_sampled']],
                    ['missing_assigned_destination', $payload['missing_assigned_destination']],
                    ['missing_and_no_source', $payload['missing_and_no_source']],
                    ['ok', $payload['ok'] ? 'true' : 'false'],
                ]
            );
        }

        return $missingAndNoSource === 0 ? self::SUCCESS : self::FAILURE;
    }
}
