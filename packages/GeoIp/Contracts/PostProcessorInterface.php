<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Contracts;

use iEXPackages\GeoIp\DTO\GeoIpContext;

interface PostProcessorInterface
{
    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function process(array $payload, GeoIpContext $ctx): array;
}
