<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Contracts;

interface IpResolverInterface
{
    public function resolve(?string $ip = null): string;
}
