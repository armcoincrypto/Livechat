<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Processors;

use iEXPackages\GeoIp\Contracts\PostProcessorInterface;
use iEXPackages\GeoIp\DTO\GeoIpContext;

final class NormalizeStringsProcessor implements PostProcessorInterface
{
    public function process(array $payload, GeoIpContext $ctx): array
    {
        return $this->walk($payload);
    }

    private function walk(mixed $v): mixed
    {
        if (is_string($v)) {
            $v = trim($v);
            if ($v === '') return null;
            return mb_strlen($v) > 255 ? mb_substr($v, 0, 255) : $v;
        }

        if (is_array($v)) {
            foreach ($v as $k => $vv) {
                $v[$k] = $this->walk($vv);
            }
            return $v;
        }

        return $v;
    }
}
