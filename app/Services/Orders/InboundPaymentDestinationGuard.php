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

        $owner = PaymentDestinationRouter::classifyDirection($direction);

        if ($owner === PaymentDestinationRouter::OWNER_KOBBOPAY) {
            return PaymentDestinationRouter::hasActiveKobbopayMerchant($direction);
        }

        if ($owner === PaymentDestinationRouter::OWNER_UNSUPPORTED) {
            return false;
        }

        if ($owner === PaymentDestinationRouter::OWNER_ZELLE_VERIFICATION) {
            return PaymentDestinationRouter::hasCanonicalRequisiteSource($direction);
        }

        return PaymentDestinationRouter::hasCanonicalRequisiteSource($direction)
            || $direction->merchants->where('status', 1)->isNotEmpty()
            || ($direction->currency1?->merchants?->where('status', 1)->isNotEmpty() ?? false);
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
