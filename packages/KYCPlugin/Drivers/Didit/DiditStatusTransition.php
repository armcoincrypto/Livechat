<?php

declare(strict_types=1);

namespace iEXPackages\KYCPlugin\Drivers\Didit;

/**
 * Monotonic Didit session-status application.
 *
 * Approved is sticky: a later expired/rejected/pending webhook must not
 * rewrite an approved session row. Retry from rejected/expired/abandoned
 * into approved is allowed.
 */
final class DiditStatusTransition
{
    public static function isApproved(string $normalized): bool
    {
        return $normalized === 'approved';
    }

    /**
     * Whether incoming normalized status may replace the stored session status.
     */
    public static function mayOverwriteSession(string $current, string $incoming): bool
    {
        $current = trim($current);
        $incoming = trim($incoming);

        if ($current === '' || $incoming === '') {
            return $incoming !== '';
        }

        if (self::isApproved($current) && !self::isApproved($incoming)) {
            return false;
        }

        return true;
    }
}
