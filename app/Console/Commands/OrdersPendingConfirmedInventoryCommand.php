<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TaskStatusEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Dry-run inventory of PENDING_PAYMENT orders that already have inbound wallet evidence.
 * Does not mutate status. Operator / payment-detector retry is the safe remediation path.
 */
final class OrdersPendingConfirmedInventoryCommand extends Command
{
    protected $signature = 'orders:pending-confirmed-inventory
        {--limit=100 : Max rows}
        {--apply : Forbidden (always refused)}';

    protected $description = 'List PENDING_PAYMENT orders with wallet txid evidence (read-only).';

    public function handle(): int
    {
        if ($this->option('apply')) {
            $this->error('REFUSED: --apply is not available. Use payment detector / operator flow after Wave2 deploy.');

            return self::FAILURE;
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        $rows = DB::table('tasks as t')
            ->join('wallet_transactions as w', 'w.id_task', '=', 't.id')
            ->where('t.status', TaskStatusEnum::PENDING_PAYMENT->value)
            ->whereNull('t.deleted_at')
            ->whereNull('w.deleted_at')
            ->whereNotNull('w.txid')
            ->where('w.txid', '!=', '')
            ->orderByDesc('t.id')
            ->limit($limit)
            ->get([
                't.id',
                't.public_id',
                't.updated_at',
                't.is_bot',
                'w.amount',
                'w.confirmations',
            ]);

        $payload = [
            'mode' => 'dry-run',
            'classification' => 'FUNDED_WRONG_STATUS_PENDING_PAYMENT',
            'count' => $rows->count(),
            'recommended_action' => 'after_wave2_deploy_recheck_payment_detector_or_operator_review',
            'automatic_mutation' => 'NO',
            'rows' => $rows->map(static function ($r) {
                return [
                    'task_id' => (int) $r->id,
                    'public_id' => (string) $r->public_id,
                    'updated_at' => (string) $r->updated_at,
                    'is_bot' => (int) $r->is_bot,
                    'amount' => $r->amount,
                    'confirmations' => (int) $r->confirmations,
                ];
            })->all(),
        ];

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
