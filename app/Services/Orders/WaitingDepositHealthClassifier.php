<?php

declare(strict_types=1);

namespace App\Services\Orders;

/**
 * Classify waiting orders that have no assigned deposit destination.
 * Pure: no DB I/O. Fail closed only for new payable holes.
 */
final class WaitingDepositHealthClassifier
{
    public const KIND_HAS_DESTINATION = 'HAS_DESTINATION';

    public const KIND_NEW_PAYABLE_ORDER_WITHOUT_DESTINATION = 'NEW_PAYABLE_ORDER_WITHOUT_DESTINATION';

    public const KIND_ZELLE_VERIFICATION_PENDING = 'ZELLE_VERIFICATION_PENDING';

    public const KIND_TEST_ARTIFACT_UNPAID = 'TEST_ARTIFACT_UNPAID';

    public const KIND_HISTORICAL_BLOCKED = 'HISTORICAL_BLOCKED';

    public const KIND_INTENTIONALLY_UNAVAILABLE = 'INTENTIONALLY_UNAVAILABLE';

    public const KIND_LEGACY_ARTIFACT = 'LEGACY_ARTIFACT';

    /** Certified fail-closed USDC order — keep visible, do not page. */
    public const KNOWN_BLOCKED_PUBLIC_IDS = [
        '1786828013012',
    ];

    /**
     * @param array{
     *   has_destination:bool,
     *   has_source:bool,
     *   owner:?string,
     *   public_id:?string,
     *   email:?string
     * } $ctx
     */
    public static function classify(array $ctx): string
    {
        if (! empty($ctx['has_destination'])) {
            return self::KIND_HAS_DESTINATION;
        }

        $email = strtolower(trim((string) ($ctx['email'] ?? '')));
        if (self::isTestArtifactEmail($email)) {
            return self::KIND_TEST_ARTIFACT_UNPAID;
        }

        $publicId = trim((string) ($ctx['public_id'] ?? ''));
        if (in_array($publicId, self::KNOWN_BLOCKED_PUBLIC_IDS, true)) {
            return self::KIND_HISTORICAL_BLOCKED;
        }

        $owner = (string) ($ctx['owner'] ?? '');
        if ($owner === PaymentDestinationRouter::OWNER_ZELLE_VERIFICATION) {
            return self::KIND_ZELLE_VERIFICATION_PENDING;
        }

        if ($owner === PaymentDestinationRouter::OWNER_KOBBOPAY) {
            return self::KIND_NEW_PAYABLE_ORDER_WITHOUT_DESTINATION;
        }

        if (! empty($ctx['has_source'])) {
            return self::KIND_NEW_PAYABLE_ORDER_WITHOUT_DESTINATION;
        }

        if ($owner === PaymentDestinationRouter::OWNER_EXSWAPING_REQUISITE
            || $owner === PaymentDestinationRouter::OWNER_UNSUPPORTED) {
            return self::KIND_INTENTIONALLY_UNAVAILABLE;
        }

        return self::KIND_LEGACY_ARTIFACT;
    }

    public static function isActionableFail(string $kind): bool
    {
        return $kind === self::KIND_NEW_PAYABLE_ORDER_WITHOUT_DESTINATION;
    }

    public static function isTestArtifactEmail(string $email): bool
    {
        if ($email === '') {
            return false;
        }

        return str_contains($email, 'canary')
            || str_ends_with($email, '@exswaping.local')
            || str_ends_with($email, '@qa.exswaping.com');
    }
}
