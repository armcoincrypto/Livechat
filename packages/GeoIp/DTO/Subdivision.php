<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\DTO;

/**
 * Административная единица: регион/район.
 */
final class Subdivision
{
    public function __construct(
        public readonly ?string $isoCode,
        public readonly ?string $name,
        public readonly ?int $geonameId,
    ) {}

    public static function fromArray(array $a): self
    {
        return new self(
            isoCode: isset($a['iso']) && is_string($a['iso']) ? $a['iso'] : null,
            name: isset($a['name']) && is_string($a['name']) ? $a['name'] : null,
            geonameId: isset($a['geoname_id']) && is_numeric($a['geoname_id']) ? (int)$a['geoname_id'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'iso' => $this->isoCode,
            'name' => $this->name,
            'geoname_id' => $this->geonameId,
        ];
    }
}
