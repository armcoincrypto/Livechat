<?php
declare(strict_types=1);

namespace iEXPackages\AMLPlugin;

use iEXPackages\AMLPlugin\Drivers\AmlBot\AmlBotDriver;
use iEXPackages\AMLPlugin\Drivers\Bitok\BitokDriver;
use iEXPackages\AMLPlugin\Drivers\GetBlock\GetBlockDriver;
use iEXPackages\AMLPlugin\Drivers\Rapira\RapiraDriver;
use iEXPackages\AMLPlugin\Drivers\Shard\ShardDriver;
use Illuminate\Support\ServiceProvider;

/**
 * Class AMLServiceProvider
 *
 * Сервис-провайдер для регистрации AMLManager и AML-драйверов.
 *
 * @package iEXPackages\AMLPlugin
 */
class AMLServiceProvider extends ServiceProvider
{
    /**
     * Регистрация сервисов и AML-драйверов.
     *
     * @return void
     */
    public function register(): void
    {
        // Регистрируем AMLManager как Singleton для всего приложения
        $this->app->singleton(AMLManager::class, fn () => new AMLManager());

        // Регистрация доступных AML-драйверов
        $this->registerDrivers();
    }

    /**
     * Инициализация сервисов после регистрации.
     *
     * @return void
     */
    public function boot(): void
    {
        // Здесь можно выполнять дополнительные действия после регистрации
    }

    /**
     * Регистрирует все доступные AML-драйверы.
     *
     * @return void
     */
    protected function registerDrivers(): void
    {
        $drivers = [
            'getblock' => GetBlockDriver::class,
            'rapira' => RapiraDriver::class,
            'amlbot' => AmlBotDriver::class,
            'bitok' => BitokDriver::class,
            'shard' => ShardDriver::class
        ];

        foreach ($drivers as $name => $driverClass) {
            AMLManager::registerDriver($name, $driverClass);
        }
    }
}
