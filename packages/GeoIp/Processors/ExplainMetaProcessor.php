<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Processors;

use iEXPackages\GeoIp\Contracts\PostProcessorInterface;
use iEXPackages\GeoIp\DTO\GeoIpContext;

final class ExplainMetaProcessor implements PostProcessorInterface
{
    public function process(array $payload, GeoIpContext $ctx): array
    {
        if (!$ctx->debugEnabled) {
            return $payload;
        }

        $payload['_meta'] = [
            'type' => $ctx->type,
            'ip' => $ctx->ip,
            'locale' => $ctx->locale,
            'source' => $ctx->source,
            'timing_ms' => $ctx->timingMs,
            'debug' => $ctx->debug,
            'processors' => $ctx->processorTrace,
        ];

        return $payload;
    }
}
