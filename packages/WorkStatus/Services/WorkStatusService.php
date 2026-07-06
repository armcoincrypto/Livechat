<?php

declare(strict_types=1);

namespace iEXPackages\WorkStatus\Services;

/**
 * HS-LC-C3 placeholder work-status service for exchange direction lookup.
 * HS-LC-D: remove or replace when clinic tenant disables direction lookup.
 */
final class WorkStatusService
{
    public function isOffline(): bool
    {
        return false;
    }
}
