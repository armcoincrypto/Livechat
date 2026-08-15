<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\AntiFraud;

final class GeoCheckResult
{
    /**
     * @param array<int,array{iso:string,name:string|null}> $allowList
     * @param array<int,array{iso:string,name:string|null}> $denyList
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly string $reason,
        public readonly ?string $countryIso,
        public readonly ?string $countryName,
        public readonly array $allowList,
        public readonly array $denyList,
    ) {}
}
