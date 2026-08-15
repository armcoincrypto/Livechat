<?php

declare(strict_types=1);

namespace iEXPackages\KYCPlugin\Drivers\Didit;

final class DiditStatusNormalizer
{
    /**
     * Normalize Didit provider status strings into Exswaping internal statuses.
     */
    public static function normalize(string $providerStatus): string
    {
        $raw = trim($providerStatus);
        $key = strtolower(str_replace(['_', '-'], ' ', $raw));
        $key = preg_replace('/\s+/', ' ', $key) ?? $key;

        return match ($key) {
            'not started' => 'not_started',
            'in progress', 'awaiting user' => 'pending',
            'in review' => 'manual_review',
            'resubmitted' => 'pending',
            'approved' => 'approved',
            'declined' => 'rejected',
            'expired', 'kyc expired' => 'expired',
            'abandoned' => 'abandoned',
            default => 'unknown',
        };
    }

    public static function maySetVerified(string $normalized): bool
    {
        return $normalized === 'approved';
    }

    public static function isTerminalRejected(string $normalized): bool
    {
        return in_array($normalized, ['rejected', 'expired', 'abandoned'], true);
    }
}
