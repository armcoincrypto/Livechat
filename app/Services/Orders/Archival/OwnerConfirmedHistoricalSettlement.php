<?php

declare(strict_types=1);

namespace App\Services\Orders\Archival;

/**
 * Owner attestation that a status=7 order was settled historically
 * outside currently observable payout markers.
 *
 * Does NOT invent payout_tx_hash / refund refs / balance mutations.
 * Uses existing tasks.is_archive + archived_at + opt_params JSON.
 */
final class OwnerConfirmedHistoricalSettlement
{
    public const RESOLUTION_TYPE = 'OWNER_CONFIRMED_HISTORICAL_SETTLEMENT';

    public const RESOLUTION_SOURCE = 'OWNER_ATTESTATION_20260929';

    public const REFERENCE_PREFIX = 'historical-status7-owner-settlement-20260929:';

    public const OPT_KEY = 'historical_resolution';

    public static function referenceForOrderId(int $orderId): string
    {
        return self::REFERENCE_PREFIX.$orderId;
    }

    /**
     * @param  array<string,mixed>|null  $optParams
     * @return array<string,mixed>|null
     */
    public static function fromOptParams(?array $optParams): ?array
    {
        if (!is_array($optParams)) {
            return null;
        }
        $res = $optParams[self::OPT_KEY] ?? null;
        if (!is_array($res)) {
            return null;
        }
        if (($res['resolution_type'] ?? null) !== self::RESOLUTION_TYPE) {
            return null;
        }

        return $res;
    }

    public static function isResolved(?array $optParams): bool
    {
        return self::fromOptParams($optParams) !== null;
    }

    /**
     * @return array<string,mixed>
     */
    public static function buildPayload(int $orderId, string $resolvedBy, ?string $resolvedAtIso = null): array
    {
        return [
            'resolution_type' => self::RESOLUTION_TYPE,
            'resolution_source' => self::RESOLUTION_SOURCE,
            'resolved_at' => $resolvedAtIso ?? now()->toIso8601String(),
            'resolved_by' => $resolvedBy,
            'reference' => self::referenceForOrderId($orderId),
            'original_status' => 7,
            'payout_evidence_fabricated' => false,
            'note' => 'Owner attested historical settlement; no observable payout markers invented.',
        ];
    }
}
