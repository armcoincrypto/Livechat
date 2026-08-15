<?php
declare(strict_types=1);

namespace iEXPackages\GeoIp\AntiFraud;

use iEXPackages\GeoIp\DTO\Location;

final class PolicyContext
{
    public function __construct(
        public readonly Location $location,
        /** @var string[] */
        public readonly array $allowIso = [],
        /** @var string[] */
        public readonly array $denyIso = [],
        public readonly bool $onlyEu = false,
        public readonly bool $readonlyCache = false,
        public readonly bool $denyIfUnknown = true,
        public readonly bool $checkRegisteredMismatch = true,
        public readonly string $registeredMismatchAction = 'review', // review|deny
        public readonly string $context = 'default', // order_create|payout|register
    ) {}
}
