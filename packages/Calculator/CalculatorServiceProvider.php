<?php

declare(strict_types=1);

namespace iEXPackages\Calculator;

use Illuminate\Support\ServiceProvider;

/**
 * CalculatorServiceProvider
 *
 * Сервис-провайдер калькулятора курсов обмена.
 *
 * Назначение:
 * - Регистрирует основной сервис Calculator в контейнере Laravel.
 * - Обеспечивает единый singleton-экземпляр калькулятора на запрос.
 * - Регистрирует алиас `calculator.exchange` для использования через фасад.
 *
 * Архитектурные особенности:
 * - Calculator создаётся как singleton, так как он:
 *   • не хранит состояние между запросами,
 *   • использует setDirectionExchange / setOrder перед расчётом,
 *   • безопасен для повторного использования в рамках одного запроса.
 *
 * Конфигурация:
 * - В конструктор Calculator передаётся конфигурация из `config/calculate.php`.
 */
final class CalculatorServiceProvider extends ServiceProvider
{
    /**
     * Регистрация сервисов в контейнере.
     *
     * Здесь мы:
     * - биндим Calculator как singleton
     * - прокидываем конфигурацию калькулятора
     * - регистрируем строковый алиас для фасада
     */
    public function register(): void
    {
        $this->app->singleton(Calculator::class, function ($app) {
            return new Calculator(
                $app['config']['calculate'] ?? []
            );
        });

        // Алиас для фасада CalculatorFacade
        $this->app->alias(Calculator::class, 'calculator.exchange');
    }

    /**
     * Bootstrap-логика после регистрации сервисов.
     *
     * Сейчас не используется, но оставлена:
     * - для возможной регистрации конфигов,
     * - событий,
     * - или дополнительных зависимостей в будущем.
     */
    public function boot(): void
    {
        // Пока не требуется
    }
}
