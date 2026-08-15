<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Traits;

trait FiatPayoutTrackingTrait
{
    public function shouldCheckByCron(): bool
    {
        return in_array($this->getTrackingMode(), ['cron', 'both'], true);
    }

    public function shouldWaitCallback(): bool
    {
        return in_array($this->getTrackingMode(), ['callback', 'both'], true);
    }

    public function isDeferredSuccess(): bool
    {
        return false;
    }
}
