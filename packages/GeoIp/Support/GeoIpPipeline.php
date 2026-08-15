<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Support;

use iEXPackages\GeoIp\Contracts\PostProcessorInterface;
use iEXPackages\GeoIp\DTO\GeoIpContext;
use Illuminate\Contracts\Container\Container;

final class GeoIpPipeline
{
    /**
     * @param string[] $processors
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $processors,
    ) {}

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function run(array $payload, GeoIpContext $ctx): array
    {
        foreach ($this->processors as $class) {
            /** @var PostProcessorInterface $p */
            $p = $this->container->make($class);
            $payload = $p->process($payload, $ctx);
            $ctx->addTrace($class);
        }

        return $payload;
    }
}
