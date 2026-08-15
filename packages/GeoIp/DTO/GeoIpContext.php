<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\DTO;

final class GeoIpContext
{
    /**
     * @param array<string,mixed> $debug
     * @param string[] $processorTrace
     */
    public function __construct(
        public readonly string $type,   // locate|country|asn
        public readonly string $ip,
        public readonly string $locale,
        public readonly string $source, // local_cache|cache_hit|computed|bypass|readonly_cache|circuit_open|skip
        public readonly bool $debugEnabled,
        public array $debug = [],
        public array $processorTrace = [],
        public ?float $timingMs = null,
    ) {}

    public function addTrace(string $processorClass): void
    {
        $this->processorTrace[] = $processorClass;
    }
}
