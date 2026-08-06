<?php

declare(strict_types=1);

namespace App\Services\Analytics;

/**
 * Maps authoritative task status transitions to funnel event names.
 * Does not invent completion semantics — mirrors TaskStatusEnum meanings.
 */
final class AttributionFunnelEventMap
{
    /** @var array<int, string> */
    public const STATUS_TO_EVENT = [
        2 => 'order_created',          // PENDING_PAYMENT
        7 => 'payment_detected',       // PAID
        3 => 'processing_started',     // WAITING_HANDLE
        4 => 'order_completed',        // COMPLETED
        1 => 'order_expired',          // EXPIRED
        6 => 'order_cancelled',        // CANCELLED
        5 => 'order_cancelled',        // REJECTED treated as cancelled for funnel
    ];

    public static function eventForStatus(int $status): ?string
    {
        return self::STATUS_TO_EVENT[$status] ?? null;
    }
}
