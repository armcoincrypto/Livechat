<?php

namespace iEXPackages\TagProcessors;

use iEXPackages\TagProcessors\Processors\ConditionalTagsProcessor;
use iEXPackages\TagProcessors\Processors\CustomTagProcessor;
use iEXPackages\TagProcessors\Processors\DirectionCityTagProcessor;
use iEXPackages\TagProcessors\Processors\EntityCustomTagProcessor;
use iEXPackages\TagProcessors\Processors\OrderTagProcessor;
use iEXPackages\TagProcessors\Processors\SystemTagProcessor;
use iEXPackages\TagProcessors\Support\TagCatalog;
use Illuminate\Support\ServiceProvider;

class TagProcessorsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Регистрируем TextTagEditor в контейнере
        $this->app->singleton(TagProcessors::class, function ($app) {

            $tagProcessors = (new TagProcessors($app['request']))
                ->registerProcessor('order', new OrderTagProcessor())
                ->registerProcessor('system', new SystemTagProcessor())
                ->registerProcessor('custom', new CustomTagProcessor())
                ->registerProcessor('entity', new EntityCustomTagProcessor())
                ->registerProcessor('direction_city', new DirectionCityTagProcessor())
                ->registerProcessor('conditionals', new ConditionalTagsProcessor());


            $tagProcessors
                // стандартный набор для публичных страниц
                ->registerProgram('public_default', ['system', 'custom']);

            return $tagProcessors;
        });

        // Новый сервис для работы с каталогом шорткодов
        $this->app->singleton(TagCatalog::class, function ($app) {
            return new TagCatalog(
                $app->make(TagProcessors::class),
                $app['request'],
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
