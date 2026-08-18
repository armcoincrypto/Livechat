<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Services\Orders\InboundPaymentDestinationGuard;
use App\Services\Orders\PaymentDestinationRouter;
use App\Services\Orders\WaitingDepositHealthClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Read-only: waiting orders that cannot show a deposit destination.
 * Does not allocate wallets or mutate tasks.
 *
 * Exit 1 only when NEW_PAYABLE_ORDER_WITHOUT_DESTINATION > 0.
 * Historical blocked / Zelle verification / test artifacts stay in counts.
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
            ->with(['direction_exchange.currency1', 'direction_exchange.merchants', 'direction_exchange.direction_requisites', 'direction_exchange.currency1.merchants'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $missingAssigned = 0;
        $missingAndNoSource = 0;
        $blockedPublicIds = [];
        $counts = [
            WaitingDepositHealthClassifier::KIND_NEW_PAYABLE_ORDER_WITHOUT_DESTINATION => 0,
            WaitingDepositHealthClassifier::KIND_ZELLE_VERIFICATION_PENDING => 0,
            WaitingDepositHealthClassifier::KIND_TEST_ARTIFACT_UNPAID => 0,
            WaitingDepositHealthClassifier::KIND_HISTORICAL_BLOCKED => 0,
            WaitingDepositHealthClassifier::KIND_INTENTIONALLY_UNAVAILABLE => 0,
            WaitingDepositHealthClassifier::KIND_LEGACY_ARTIFACT => 0,
        ];
        $newPayablePublicIds = [];

        foreach ($waiting as $task) {
            $hasDest = InboundPaymentDestinationGuard::taskHasAssignedDestination($task);
            $dir = $task->direction_exchange;
            $hasSource = $dir !== null && InboundPaymentDestinationGuard::directionHasSource($dir);
            $owner = $dir !== null
                ? PaymentDestinationRouter::classifyDirection($dir)
                : PaymentDestinationRouter::OWNER_UNSUPPORTED;

            $kind = WaitingDepositHealthClassifier::classify([
                'has_destination' => $hasDest,
                'has_source' => $hasSource,
                'owner' => $owner,
                'public_id' => (string) $task->public_id,
                'email' => (string) $task->email,
            ]);

            if ($kind === WaitingDepositHealthClassifier::KIND_HAS_DESTINATION) {
                continue;
            }

            $missingAssigned++;
            if (! $hasSource) {
                $missingAndNoSource++;
                $blockedPublicIds[] = (string) $task->public_id;
            }
            if (isset($counts[$kind])) {
                $counts[$kind]++;
            }
            if ($kind === WaitingDepositHealthClassifier::KIND_NEW_PAYABLE_ORDER_WITHOUT_DESTINATION) {
                $newPayablePublicIds[] = (string) $task->public_id;
            }
        }

        $newPayable = $counts[WaitingDepositHealthClassifier::KIND_NEW_PAYABLE_ORDER_WITHOUT_DESTINATION];
        $ok = $newPayable === 0;

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'metric' => 'waiting_crypto_order_without_deposit_destination',
            'waiting_sampled' => $waiting->count(),
            'missing_assigned_destination' => $missingAssigned,
            'missing_and_no_source' => $missingAndNoSource,
            'ok' => $ok,
            'blocked_public_ids' => $blockedPublicIds,
            'WAITING_PAYMENT_WITHOUT_USABLE_DESTINATION' => $missingAssigned,
            'NEW_PAYABLE_ORDER_WITHOUT_DESTINATION' => $newPayable,
            'new_payable_public_ids' => $newPayablePublicIds,
            'counts_by_kind' => $counts,
        ];

        Log::info('orders:waiting-deposit-health', [
            'ok' => $ok,
            'NEW_PAYABLE_ORDER_WITHOUT_DESTINATION' => $newPayable,
            'missing_and_no_source' => $missingAndNoSource,
            'missing_assigned_destination' => $missingAssigned,
            'counts_by_kind' => $counts,
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
                    ['NEW_PAYABLE_ORDER_WITHOUT_DESTINATION', $newPayable],
                    ['ok', $ok ? 'true' : 'false'],
                ]
            );
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
