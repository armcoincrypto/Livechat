<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp;

use iEXPackages\GeoIp\DTO\Location;

/**
 * HS-LC-C3 placeholder GeoIP service (returns unknown location).
 * HS-LC-D: wire real GeoIP lookup or remove if not needed for clinic.
 */
final class GeoIp
{
    public function country(?string $ip = null, ?string $locale = null): Location
    {
        return Location::empty($ip !== null && $ip !== '' ? $ip : '0.0.0.0');
    }
}
