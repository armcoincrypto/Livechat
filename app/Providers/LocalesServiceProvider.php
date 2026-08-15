<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Locales\LanguageCatalog;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

final class LocalesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LanguageCatalog::class, function ($app) {
            return new LanguageCatalog(
                files: $app->make(Filesystem::class),
                cache: $app->bound(CacheRepository::class) ? $app->make(CacheRepository::class) : null,
                relativePath: 'app/iexexchanger/locales',
                cacheTtlSeconds: 300,
            );
        });
    }
}
