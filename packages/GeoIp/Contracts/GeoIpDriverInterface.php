<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Contracts;

use iEXPackages\GeoIp\DTO\Location;

interface GeoIpDriverInterface
{
    public function locate(string $ip, string $locale): Location;
    public function country(string $ip, string $locale): Location;
    public function asn(string $ip, string $locale): Location;
}
