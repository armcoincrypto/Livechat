<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Drivers;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use iEXPackages\GeoIp\Contracts\GeoIpDriverInterface;
use iEXPackages\GeoIp\DTO\Location;
use iEXPackages\GeoIp\DTO\Subdivision;
use iEXPackages\GeoIp\Exceptions\DatabaseNotFoundException;
use iEXPackages\GeoIp\Support\SafeExtractor;

/**
 * MaxMind mmdb driver + hot-reload по filemtime().
 */
final class MaxMindDatabaseDriver implements GeoIpDriverInterface
{
    private ?Reader $cityReader = null;
    private ?Reader $countryReader = null;
    private ?Reader $asnReader = null;

    private int $cityMtime = 0;
    private int $countryMtime = 0;
    private int $asnMtime = 0;

    public function __construct(
        private readonly array $paths, // ['city'=>..., 'country'=>..., 'asn'=>...]
    ) {}

    public function locate(string $ip, string $locale): Location
    {
        try {
            $m = $this->cityReader()->city($ip);

            $subs = [];
            foreach (($m->subdivisions ?? []) as $s) {
                $subs[] = new Subdivision(
                    isoCode: SafeExtractor::str($s->isoCode ?? null),
                    name: SafeExtractor::name($s, $locale),
                    geonameId: SafeExtractor::int($s->geonameId ?? null),
                );
            }

            $regionIso  = $subs[0]->isoCode ?? null;
            $regionName = $subs[0]->name ?? null;

            $most = $m->mostSpecificSubdivision ?? null;

            return new Location(
                ip: $ip,

                continentCode: SafeExtractor::str($m->continent->code ?? null),
                continentName: SafeExtractor::name($m->continent ?? null, $locale),
                continentGeonameId: SafeExtractor::int($m->continent->geonameId ?? null),

                countryIso: SafeExtractor::str($m->country->isoCode ?? null),
                countryName: SafeExtractor::name($m->country ?? null, $locale),
                countryGeonameId: SafeExtractor::int($m->country->geonameId ?? null),
                countryIsEU: SafeExtractor::bool($m->country->isInEuropeanUnion ?? null),

                registeredCountryIso: SafeExtractor::str($m->registeredCountry->isoCode ?? null),
                registeredCountryName: SafeExtractor::name($m->registeredCountry ?? null, $locale),
                registeredCountryGeonameId: SafeExtractor::int($m->registeredCountry->geonameId ?? null),
                registeredCountryIsEU: SafeExtractor::bool($m->registeredCountry->isInEuropeanUnion ?? null),

                cityName: SafeExtractor::name($m->city ?? null, $locale),
                cityGeonameId: SafeExtractor::int($m->city->geonameId ?? null),

                regionIso: $regionIso,
                regionName: $regionName,

                districtIso: SafeExtractor::str($most->isoCode ?? null),
                districtName: SafeExtractor::name($most, $locale),

                subdivisions: $subs,

                postalCode: SafeExtractor::str($m->postal->code ?? null),

                latitude: SafeExtractor::float($m->location->latitude ?? null),
                longitude: SafeExtractor::float($m->location->longitude ?? null),
                accuracyRadius: SafeExtractor::int($m->location->accuracyRadius ?? null),
                timeZone: SafeExtractor::str($m->location->timeZone ?? null),

                network: SafeExtractor::str($m->traits->network ?? null),
                asn: SafeExtractor::int($m->traits->autonomousSystemNumber ?? null),
                asnOrg: SafeExtractor::str($m->traits->autonomousSystemOrganization ?? null),

                isTorExitNode: SafeExtractor::bool($m->traits->isTorExitNode ?? null),
                isAnonymousVpn: SafeExtractor::bool($m->traits->isAnonymousVpn ?? null),
                isHostingProvider: SafeExtractor::bool($m->traits->isHostingProvider ?? null),
            );
        } catch (AddressNotFoundException) {
            return Location::empty($ip);
        }
    }

    public function country(string $ip, string $locale): Location
    {
        $path = (string)($this->paths['country'] ?? '');
        if ($path === '' || !is_file($path)) {
            return Location::empty($ip);
        }

        try {
            $m = $this->countryReader()->country($ip);

            return new Location(
                ip: $ip,
                continentCode: SafeExtractor::str($m->continent->code ?? null),
                continentName: SafeExtractor::name($m->continent ?? null, $locale),
                continentGeonameId: SafeExtractor::int($m->continent->geonameId ?? null),
                countryIso: SafeExtractor::str($m->country->isoCode ?? null),
                countryName: SafeExtractor::name($m->country ?? null, $locale),
                countryGeonameId: SafeExtractor::int($m->country->geonameId ?? null),
                countryIsEU: SafeExtractor::bool($m->country->isInEuropeanUnion ?? null),
                network: SafeExtractor::str($m->traits->network ?? null),
            );
        } catch (AddressNotFoundException) {
            return Location::empty($ip);
        }
    }

    public function asn(string $ip, string $locale): Location
    {
        $path = (string)($this->paths['asn'] ?? '');
        if ($path === '' || !is_file($path)) {
            return Location::empty($ip);
        }

        try {
            $m = $this->asnReader()->asn($ip);

            return new Location(
                ip: $ip,
                asn: SafeExtractor::int($m->autonomousSystemNumber ?? null),
                asnOrg: SafeExtractor::str($m->autonomousSystemOrganization ?? null),
                network: SafeExtractor::str($m->network ?? null),
            );
        } catch (AddressNotFoundException) {
            return Location::empty($ip);
        }
    }

    private function cityReader(): Reader
    {
        $path = (string)($this->paths['city'] ?? '');
        if ($path === '' || !is_file($path)) {
            throw new DatabaseNotFoundException("Не найдена база GeoLite2 City: {$path}");
        }

        $mtime = (int)@filemtime($path);
        if (!$this->cityReader || ($mtime > 0 && $mtime !== $this->cityMtime)) {
            $this->cityReader = new Reader($path);
            $this->cityMtime = $mtime;
        }

        return $this->cityReader;
    }

    private function countryReader(): Reader
    {
        $path = (string)($this->paths['country'] ?? '');
        if ($path === '' || !is_file($path)) {
            throw new DatabaseNotFoundException("Не найдена база GeoLite2 Country: {$path}");
        }

        $mtime = (int)@filemtime($path);
        if (!$this->countryReader || ($mtime > 0 && $mtime !== $this->countryMtime)) {
            $this->countryReader = new Reader($path);
            $this->countryMtime = $mtime;
        }

        return $this->countryReader;
    }

    private function asnReader(): Reader
    {
        $path = (string)($this->paths['asn'] ?? '');
        if ($path === '' || !is_file($path)) {
            throw new DatabaseNotFoundException("Не найдена база GeoLite2 ASN: {$path}");
        }

        $mtime = (int)@filemtime($path);
        if (!$this->asnReader || ($mtime > 0 && $mtime !== $this->asnMtime)) {
            $this->asnReader = new Reader($path);
            $this->asnMtime = $mtime;
        }

        return $this->asnReader;
    }
}
