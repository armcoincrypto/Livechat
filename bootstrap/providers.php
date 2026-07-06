<?php

declare(strict_types=1);

return [
    App\Providers\AppServiceProvider::class,
    iEXPackages\GeoIp\GeoIpServiceProvider::class,
    iEXPackages\WorkStatus\WorkStatusServiceProvider::class,
    iEXPackages\SupportChat\SupportChatServiceProvider::class,
];
