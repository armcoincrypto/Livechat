<?php
declare(strict_types=1);

namespace iEXPackages\SmartMailer;

use iEXPackages\SmartMailer\Contracts\MailDispatcherContract;
use iEXPackages\SmartMailer\Services\SmartMailerService;
use Illuminate\Support\ServiceProvider;

/**
 * Провайдер сервиса SmartMailer.
 *
 * Отвечает за регистрацию сервисов и условий отправки писем пакета SmartMailer.
 */
class SmartMailerServiceProvider extends ServiceProvider
{
    /**
     * Регистрация сервисов в контейнере приложения.
     *
     * @return void
     */
    public function register(): void
    {
        // Регистрация основного сервиса отправки писем как Singleton
        $this->app->singleton(MailDispatcherContract::class, SmartMailerService::class);

        // Регистрация условий отправки писем
        $this->registerMailConditions();
    }

    /**
     * Инициализация пакета после загрузки всех сервисов.
     *
     * @return void
     */
    public function boot(): void
    {
        // Регистрация views шаблонов писем пакета
        $this->loadViewsFrom(__DIR__.'/Resources/Views', 'smart-mailer');
        $this->loadTranslationsFrom(__DIR__.'/Resources/Lang', 'smart-mailer');

    }

    /**
     * Регистрирует условия отправки писем, указанные в SmartMailerConditionFactory.
     *
     * @return void
     */
    protected function registerMailConditions(): void
    {
        foreach (SmartMailerConditionFactory::conditions() as $conditionClass) {
            $this->app->bind($conditionClass);
        }
    }
}
