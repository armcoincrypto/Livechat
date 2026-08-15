<?php
// iexpackages/AuthAudit/src/Context/DeviceIdFactory.php

declare(strict_types=1);

namespace iEXPackages\AuthAudit\Context;

final class DeviceIdFactory
{
    /**
     * Стабильный device_id: UA + accept-language + sec-ch-ua-platform.
     * Достаточно для “новый/известный девайс”.
     */
    public function make(?string $userAgent, ?string $acceptLanguage, ?string $secChUaPlatform): string
    {
        $ua = (string)($userAgent ?? '');
        $al = (string)($acceptLanguage ?? '');
        $pf = (string)($secChUaPlatform ?? '');

        return hash('sha256', $ua . '|' . $al . '|' . $pf);
    }

    public function short(string $hash): string
    {
        return $hash;
    }
}
