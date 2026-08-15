<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Contracts;

interface LocationInterface
{
    public function toArray(): array;
}
