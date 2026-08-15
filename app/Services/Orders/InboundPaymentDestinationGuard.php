<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\DirectionExchange;
use App\Models\Requisites;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

/**
 * Read-only checks for inbound payment destination availability.
 * Does not allocate wallets, call merchants, or mutate orders.
 */
final class InboundPaymentDestinationGuard
{
    public const ERROR_PAYMENT_DESTINATION_UNAVAILABLE = 'PAYMENT_DESTINATION_UNAVAILABLE';

    public static function directionHasSource(DirectionExchange $direction): bool
    {
        $direction->loadMissing([
            'currency1.merchants',
            'merchants',
            'direction_requisites',
        ]);

        $in = $direction->currency1;
        if ($in === null) {
            return false;
        }

        if ((int) ($direction->method_request_payment ?? 0) === 1) {
            return true;
        }
        if ((int) ($in->method_request_payment ?? 0) === 1) {
            return true;
        }

        if ($direction->merchants->where('status', 1)->isNotEmpty()) {
            return true;
        }
        if ($in->merchants->where('status', 1)->isNotEmpty()) {
            return true;
        }

        if ($direction->direction_requisites->where('status', 1)->isNotEmpty()) {
            return true;
        }

        return Requisites::activeWallet()->where('id_currency', $in->id)->exists();
    }

    /**
     * Snapshot already assigned to a task (no lazy allocation).
     */
    public static function taskHasAssignedDestination(Task $task): bool
    {
        if (trim((string) ($task->transfer_to_account ?? '')) !== '') {
            return true;
        }

        $mtd = DB::table('merchants_transaction_data')->where('id_task', $task->id)->first();
        if ($mtd === null) {
            return false;
        }

        $ext = is_string($mtd->ext_data ?? null)
            ? json_decode((string) $mtd->ext_data, true)
            : (array) ($mtd->ext_data ?? []);
        if (! is_array($ext)) {
            return false;
        }

        if (trim((string) ($ext['wallet_number'] ?? '')) !== '') {
            return true;
        }

        $checkout = is_array($ext['checkout'] ?? null) ? $ext['checkout'] : [];

        return trim((string) ($checkout['url'] ?? '')) !== '';
    }
}
