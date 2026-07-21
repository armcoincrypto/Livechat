<?php

namespace App\Console\Schedule;

use Illuminate\Console\Scheduling\Schedule;

class ExchangeSchedule
{
    /**
     * Регистрирует все cron-задачи обменника.
     *
     * Точка входа, вызываемая из app.php через withSchedule().
     *
     * @param Schedule $schedule
     * @return void
     */
    public static function register(Schedule $schedule): void
    {
        static::registerCoreMaintenance($schedule);
        static::registerStatisticsAndProfit($schedule);
        static::registerCompilerAndRates($schedule);
        static::registerMonitoringAndNotifications($schedule);
    }

    /**
     * Базовые сервисные задачи: логи, sitemap, мерчанты, выводы, резервы.
     *
     * @param Schedule $schedule
     * @return void
     */
    private static function registerCoreMaintenance(Schedule $schedule): void
    {
        // Очистка логов
        $schedule->command('logs:cleanup')->dailyAt('02:00');

        $schedule->command('bans:purge --expired')
            ->daily();

        $schedule
            ->command('visits:cleanup 1440 90')
            ->weekly()
            ->withoutOverlapping()
            ->onOneServer();


        $schedule->command('order-recount:aggregate-daily')
            ->dailyAt('00:20')
            ->onOneServer()
            ->withoutOverlapping();

        // Пересчёт заявок по cron (OrderRecount)
        // Команда сама проверяет настройку type_recalculation_order и ничего не сделает, если cron-режим выключен.
        $schedule->command('order-recount:run --details')
            ->everyMinute()
            ->onOneServer()
            ->withoutOverlapping(50)
            ->appendOutputTo(storage_path('logs/order_recount_run.log'));



        // Автоматическое обновление карты сайта (каждый день)
        $schedule->command('update:sitemap')->daily();

        // Обновление деталей мерчанта каждую минуту
        $schedule->command('merchant:webhook')
            ->everyMinute()
            ->withoutOverlapping(55)
            ->runInBackground();

        // Восстановление invoice/create после сбоя checkout (Exnode и др. с тем же transactionId)
        $schedule->command('merchant:recover-checkout-invoices --limit=50')
            ->everyTwoMinutes()
            ->onOneServer()
            ->withoutOverlapping(5)
            ->runInBackground();

        // 1) Ставим оплаченные заявки в очередь на выплату (7 -> 16)
        $schedule->command('merchant:autopay-queue --chunk=10 --limit=10')
            ->everyMinute()
            ->onOneServer()
            ->withoutOverlapping(3)
            ->appendOutputTo(storage_path('logs/autopay_queue.log'));

        // 2) Запускаем авто-выплаты по заявкам из очереди (16)
        $schedule->command('merchant:autopay-run --chunk=10 --limit=10')
            ->everyMinute()
            ->onOneServer()
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/autopay_run.log'));

        // Обновление кеша BestChange
        $schedule->command('bestchange:cache-update')->hourly();

        // Приём callback-хэшей от мерчантов
        //$schedule->command('callback:receive_hash')->everyMinute();

        // Обработка ожидающих выплат
        $schedule->command('pay:pending-withdrawal')->everyMinute();

        // Обновление резервов из файла/сервера (если включено в настройках)
        if (iEXSetting('is_enabled_reserves_from_file') || iEXSetting('is_enabled_reserves_from_server')) {
            $schedule->command('reserve:update')->everyMinute();
        }

        // Очистка временных экспортов
        $schedule->command('exports:cleanup')->daily();

        // Ежедневное удаление старых логов
        $schedule->command('log:clears')->dailyAt('02:00');

        // Удаление просроченных банов
        $schedule->command('ban:delete-expired')->hourly();

        // Обновление адресов proxyfilter (каждый день в 07:00)
        $schedule->command('proxyfilter:reload')->dailyAt('07:00');



            // 1 COUNT / minute — безопасно и предсказуемо
        $schedule->command('presence:snapshot')->everyMinute();

        // daily rollup за вчера — лучше ночью
        $schedule->command('presence:rollup-daily')->dailyAt('00:10');

        // чистка старых sessions (гости удаляются, статистика остаётся)
        $schedule->command('presence:prune')->dailyAt('03:10');
    }

    /**
     * Задачи по статистике, прибыли и агрегированным срезам.
     *
     * @param Schedule $schedule
     * @return void
     */
    private static function registerStatisticsAndProfit(Schedule $schedule): void
    {
        // Пересчёт прибыли за вчера каждый день в 00:20
        $schedule->command('profit:recalculate-daily')->dailyAt('00:20');

        // Снимок общих резервов каждые 15 минут
        $schedule->command('reserves:total-snapshot')->everyFifteenMinutes();

        // Каждый час пересчитываем статистику направлений за последние дни
        $schedule->command('stats:directions-daily --from=' . now()->subDays(2)->toDateString() . ' --to=' . now()->toDateString())
            ->hourly()
            ->withoutOverlapping();

        // Ежедневная статистика по заявкам
        $schedule->command('stats:orders-daily')->dailyAt('00:15');

        // Ежедневная статистика по валютам
        $schedule->command('stats:currencies-daily')->dailyAt('00:10');

        // Ежедневная агрегация ledger резервов (витрина reserve_ledger_daily)
        // Пересчитываем за вчера, когда все заявки уже закрыты
        $schedule->command('reserve-ledger:daily --days=1')
            ->dailyAt('00:30')
            ->onOneServer()
            ->withoutOverlapping();
    }

    /**
     * Обновление курсов, схем и цен (основной компилятор курсов).
     *
     * @param Schedule $schedule
     * @return void
     */
    private static function registerCompilerAndRates(Schedule $schedule): void
    {
        $scheduleMap = [
            1  => 'everyTenSeconds',
            2  => 'everyFifteenSeconds',
            3  => 'everyTwentySeconds',
            4  => 'everyThirtySeconds',
            5  => 'everyTwoMinutes',
            6  => 'everyThreeMinutes',
            7  => 'everyFiveMinutes',
            8  => 'everyFiveSeconds',
            9  => 'everyTwoSeconds',
            10 => 'everySecond',
        ];

        $compilerTime = $scheduleMap[(int) iEXSetting('cron_interval_minutes_update_rates', 0)] ?? 'everyMinute';
        $compilerCoursesTime = $scheduleMap[(int) iEXSetting('grates_cron_timer', 0)] ?? 'everyMinute';



        $schedule->command('compiler:courses --isUpdate=0')
            ->{$compilerTime}()
            ->onOneServer()
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/compiler_courses.log'));

        $schedule->command('compiler:bestchange')
            ->{$compilerTime}()
            ->onOneServer()
            ->withoutOverlapping(180)
            ->appendOutputTo(storage_path('logs/compiler_bestchange.log'));

        // Проверка и обновление файлов курсов
        $schedule->command('scheme:files')
            ->{$compilerCoursesTime}()
            ->onOneServer()
            ->withoutOverlapping(5);

        // Read-only rate pipeline health (non-zero exit on critical conditions).
        $schedule->command('rates:health --format=json')
            ->everyFiveMinutes()
            ->onOneServer()
            ->withoutOverlapping(5)
            ->appendOutputTo(storage_path('logs/rates_health.log'));

        $schedule->command('rates:rub-catalog-monitor --format=json')
            ->everyFiveMinutes()
            ->onOneServer()
            ->withoutOverlapping(5)
            ->appendOutputTo(storage_path('logs/rates_rub_catalog_monitor.log'));

        // Генерация минимальной и максимальной цены
        $schedule->command('compiler:generate_prices')->everyFiveMinutes();

        // Обновление файлов BestChange (полный набор), раз в день
        $schedule->command('bestchange:files')->daily();
    }

    /**
     * Мониторинг системы и вспомогательные уведомления.
     *
     * @param Schedule $schedule
     * @return void
     */
    private static function registerMonitoringAndNotifications(Schedule $schedule): void
    {
        // Short-lived Pulse server snapshot (replaces supervisor long-running pulse:check daemon).
        $schedule->command('pulse:check --once')
            ->everyMinute()
            ->onOneServer()
            ->withoutOverlapping(55);

        $schedule->command('proxy:prune-health-logs --days=30')->dailyAt('03:15');

        // Часовой мониторинг данных
        $schedule->command('monitoring:hourly')->hourly();

        // Ежедневное обновление мониторинговых срезов
        $schedule->command('monitoring:daily')->dailyAt('03:00');

        // Каждые 5 минут — снимок состояния Horizon
        $schedule->command('horizon:snapshot')->everyFiveMinutes();

        // Ежедневная статистика для Telegram-бота/канала
        $schedule->command('iextelegram:stat')->dailyAt('23:58');

        // LC-P10.1: privacy-safe hourly Support Chat activity report (guarded by env + command registration)
        if (
            filter_var(config('support_chat.telegram.hourly_report.enabled'), FILTER_VALIDATE_BOOLEAN)
            && \Illuminate\Support\Facades\Artisan::has('support-chat:hourly-report')
        ) {
            $schedule->command('support-chat:hourly-report --send')
                ->hourly()
                ->onOneServer()
                ->withoutOverlapping(55);
        }

        // TRAFFIC-P2a.1: site activity report every 5 hours (primary operational visibility)
        if (filter_var(config('traffic.site_report.schedule_enabled'), FILTER_VALIDATE_BOOLEAN)) {
            $periodHours = max(1, (int) config('traffic.site_report.period_hours', 5));
            $schedule->command("traffic:hourly-report --send --hours={$periodHours}")
                ->cron('0 */5 * * *')
                ->onOneServer()
                ->withoutOverlapping(55);
        }
    }
}
