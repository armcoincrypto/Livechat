<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Certification\CertificationContext;
use App\Services\Certification\CertificationForbiddenKobbopayHttpClient;
use iEXPackages\Payments\Core\Contracts\GatewayHttpClientInterface;
use Illuminate\Support\ServiceProvider;

final class OrderCertificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CertificationContext::class);

        $this->app->extend(GatewayHttpClientInterface::class, function ($inner, $app) {
            return new CertificationForbiddenKobbopayHttpClient(
                $inner,
                $app->make(CertificationContext::class),
            );
        });
    }
}
