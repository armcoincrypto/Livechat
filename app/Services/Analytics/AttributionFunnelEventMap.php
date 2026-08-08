<?php

declare(strict_types=1);

namespace App\Services\Analytics;

/**
 * Maps authoritative task status transitions to funnel event names.
 * Does not invent completion semantics — mirrors TaskStatusEnum meanings.
 *
 * C.3E: only approved conceptual events. Unmapped terminal/nonterminal
 * statuses must not silently become order_cancelled.
 */
final class AttributionFunnelEventMap
{
    /**
     * Status → event. Multiple statuses may share one event name; uniqueness
     * is enforced per (task_id, event_type) so the first transition wins.
     *
     * @var array<int, string>
     */
    public const STATUS_TO_EVENT = [
        2 => 'order_created',       // PENDING_PAYMENT
        7 => 'payment_detected',    // PAID (backend-confirmed payment)
        3 => 'processing_started',  // WAITING_HANDLE (manual processing)
        16 => 'processing_started', // PAYOUT_QUEUE (autopay processing entry)
        4 => 'order_completed',     // COMPLETED
        1 => 'order_expired',       // EXPIRED
        6 => 'order_cancelled',     // CANCELED_BY_USER
    ];

    /**
     * Terminal (or bad-final) statuses intentionally not mapped in C.3E.
     *
     * @var list<int>
     */
    public const UNMAPPED_TERMINAL = [
        5,  // REJECTED — distinct from user cancel; do not funnel-merge
        10, // INVALID
        11, // DELETED
    ];

    /**
     * Nonterminal statuses observed in payment/processing flows without a
     * dedicated C.3E event (inventory for reporting design).
     *
     * @var list<int>
     */
    public const UNMAPPED_NONTERMINAL = [
        8,  // FROZEN
        9,  // PROCESSING_PAYMENT
        12, // CHECK_PAYMENT
        13, // MERCHANT_CONFIRMATION
        14, // AUTO_PAYOUT_ERROR
        15, // PAYOUT_IN_PROGRESS (processing_started already emitted at 16 or 3)
    ];

    public static function eventForStatus(int $status): ?string
    {
        return self::STATUS_TO_EVENT[$status] ?? null;
    }

    /** @return list<string> */
    public static function approvedEventTypes(): array
    {
        return array_values(array_unique(array_values(self::STATUS_TO_EVENT)));
    }
}
