<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade GeoIP
 *
 * Примеры:
 *   $loc = \iEXPackages\GeoIp\Facades\GeoIP::locate();
 *   $loc = \iEXPackages\GeoIp\Facades\GeoIP::country('8.8.8.8', 'ru');
 *   $map = \iEXPackages\GeoIp\Facades\GeoIP::bulkLocate(['8.8.8.8','1.1.1.1']);
 *
 * @method static \iEXPackages\GeoIp\DTO\Location locate(?string $ip = null, ?string $locale = null)
 * @method static \iEXPackages\GeoIp\DTO\Location country(?string $ip = null, ?string $locale = null)
 * @method static \iEXPackages\GeoIp\DTO\Location asn(?string $ip = null, ?string $locale = null)
 *
 * @method static array<string,\iEXPackages\GeoIp\DTO\Location> bulkLocate(array $ips, ?string $locale = null)
 * @method static array<string,\iEXPackages\GeoIp\DTO\Location> bulkCountry(array $ips, ?string $locale = null)
 * @method static array<string,\iEXPackages\GeoIp\DTO\Location> bulkAsn(array $ips, ?string $locale = null)
 */
final class GeoIP extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \iEXPackages\GeoIp\GeoIp::class;
    }
}
