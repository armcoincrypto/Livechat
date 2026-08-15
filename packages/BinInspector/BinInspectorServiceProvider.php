<?php

declare(strict_types=1);

namespace iEXPackages\BinInspector;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Сервис-провайдер для регистрации BinInspector в приложении.
 */
final class BinInspectorServiceProvider extends ServiceProvider
{
    /**
     * Регистрация сервиса в контейнере.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(BinInspector::class, function ($app) {
            /** @var HttpFactory $http */
            $http = $app->make(HttpFactory::class);

            return new BinInspector($http);
        });

        // Алиас для фасада
        $this->app->alias(BinInspector::class, 'bin-inspector');
    }

    /**
     * Загрузка дополнительных ресурсов (если будут нужны в будущем).
     *
     * @return void
     */
    public function boot(): void
    {
        // Сейчас дополнительных ресурсов нет — оставляем метод пустым.
    }
}
