<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\DTO;

/**
 * HS-LC-C3 placeholder GeoIP location DTO.
 * Replace with full GeoIp package or external service in a later phase.
 */
final class Location
{
    public function __construct(
        public readonly string $ip = '0.0.0.0',
        public readonly ?string $countryIso = null,
        public readonly ?string $countryName = null,
        public readonly ?string $timeZone = null,
    ) {}

    public static function empty(string $ip = '0.0.0.0'): self
    {
        return new self(ip: $ip);
    }

    public function countryIso(): ?string
    {
        return $this->countryIso;
    }

    public function countryName(): ?string
    {
        return $this->countryName;
    }

    public function flagEmoji(): ?string
    {
        return null;
    }
}
