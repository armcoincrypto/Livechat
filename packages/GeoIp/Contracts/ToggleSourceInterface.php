<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Contracts;

interface ToggleSourceInterface
{
    public function get(string $key): mixed;
}
