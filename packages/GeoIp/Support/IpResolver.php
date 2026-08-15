<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Support;

use iEXPackages\GeoIp\Contracts\IpResolverInterface;
use iEXPackages\GeoIp\Exceptions\InvalidIpException;
use Illuminate\Http\Request;

/**
 * Безопасное определение IP.
 */
final class IpResolver implements IpResolverInterface
{
    public function __construct(
        private readonly ?Request $request,
        private readonly string $source,
    ) {}

    public function resolve(?string $ip = null): string
    {
        $candidate = trim((string)$ip);

        if ($candidate === '') {
            $candidate = $this->source === 'remote_addr_only'
                ? trim((string)($this->request?->server('REMOTE_ADDR') ?? ''))
                : trim((string)($this->request?->ip() ?? ''));
        }

        if ($candidate === '' || !Ip::isValid($candidate)) {
            throw new InvalidIpException("Некорректный IP: {$candidate}");
        }

        return $candidate;
    }
}
