<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp;

use Illuminate\Support\ServiceProvider;

final class GeoIpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GeoIp::class, static fn (): GeoIp => new GeoIp());
    }
}
