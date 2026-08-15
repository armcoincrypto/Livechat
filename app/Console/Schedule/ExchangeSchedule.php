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

        $schedule->command('orders:funded-health --alert')
            ->everyFiveMinutes()
            ->onOneServer()
            ->withoutOverlapping(4)
            ->runInBackground();

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
     * Resolve operator "minutes" settings to a Laravel schedule cadence.
     *
     * Historical bug: values 1–10 were mapped to sub-minute methods (value 1 →
     * everyTenSeconds), which starved the shared heavy lock. The setting names
     * (`cron_interval_minutes_update_rates`, `grates_cron_timer`) mean minutes.
     * Value `1` is once per minute. Sub-minute behavior requires a separately
     * named seconds setting and is intentionally not overloaded here.
     *
     * @return array{0: 'method'|'cron', 1: string}
     */
    public static function resolveMinuteCadence(int $minutes): array
    {
        $minutes = $minutes <= 0 ? 1 : $minutes;

        return match (true) {
            $minutes === 1 => ['method', 'everyMinute'],
            $minutes === 2 => ['method', 'everyTwoMinutes'],
            $minutes === 3 => ['method', 'everyThreeMinutes'],
            $minutes === 4 => ['method', 'everyFourMinutes'],
            $minutes === 5 => ['method', 'everyFiveMinutes'],
            $minutes === 10 => ['method', 'everyTenMinutes'],
            $minutes === 15 => ['method', 'everyFifteenMinutes'],
            $minutes === 30 => ['method', 'everyThirtyMinutes'],
            $minutes >= 60 => ['method', 'hourly'],
            default => ['cron', sprintf('*/%d * * * *', min(59, $minutes))],
        };
    }

    /**
     * @param  \Illuminate\Console\Scheduling\Event|\Illuminate\Console\Scheduling\CallbackEvent  $event
     */
    public static function applyMinuteCadence(object $event, int $minutes): void
    {
        [$kind, $value] = self::resolveMinuteCadence($minutes);
        if ($kind === 'method') {
            $event->{$value}();
            return;
        }
        $event->cron($value);
    }

    /**
     * Обновление курсов, схем и цен (основной компилятор курсов).
     *
     * @param Schedule $schedule
     * @return void
     */
    private static function registerCompilerAndRates(Schedule $schedule): void
    {
        $ratesMinutes = (int) iEXSetting('cron_interval_minutes_update_rates', 1);
        $exportMinutes = (int) iEXSetting('grates_cron_timer', 1);
        $compilerWrapper = '/opt/exswaping-owned-frontend/scripts/ops/run_backend_compiler.sh';

        $courses = $schedule->exec("{$compilerWrapper} compiler:courses --isUpdate=0")
            ->onOneServer()
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/compiler_courses.log'));
        self::applyMinuteCadence($courses, $ratesMinutes);

        $bestchange = $schedule->exec("{$compilerWrapper} compiler:bestchange")
            ->onOneServer()
            ->withoutOverlapping(180)
            ->appendOutputTo(storage_path('logs/compiler_bestchange.log'));
        self::applyMinuteCadence($bestchange, $ratesMinutes);

        // ZELLEUSD outgoing: refresh course_value from USDTTRC20→dest after BC peer updates.
        $zelle = $schedule->command('rates:zelleusd-usdt-preview --all --apply')
            ->onOneServer()
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/zelle_usdt_benchmark.log'));
        self::applyMinuteCadence($zelle, $ratesMinutes);

        // Проверка и обновление файлов курсов / public XML exports
        $scheme = $schedule->exec("{$compilerWrapper} scheme:files")
            ->onOneServer()
            ->withoutOverlapping(10);
        self::applyMinuteCadence($scheme, $exportMinutes);
        // Read-only rate pipeline health (non-zero exit on critical conditions).
        $schedule->command('rates:health --format=json')
            ->everyFiveMinutes()
            ->onOneServer()
            ->withoutOverlapping(5)
            ->appendOutputTo(storage_path('logs/rates_health.log'));

        $schedule->command('orders:waiting-deposit-health --format=json')
            ->everyFiveMinutes()
            ->onOneServer()
            ->withoutOverlapping(5)
            ->appendOutputTo(storage_path('logs/orders_waiting_deposit_health.log'));

        $schedule->command('orders:payment-routing-health --format=json')
            ->everyFiveMinutes()
            ->onOneServer()
            ->withoutOverlapping(5)
            ->appendOutputTo(storage_path('logs/orders_payment_routing_health.log'));

        // Генерация минимальной и максимальной цены
        $schedule->exec("{$compilerWrapper} compiler:generate_prices")
            ->everyFiveMinutes()
            ->onOneServer()
            ->withoutOverlapping(10);

        // Обновление файлов BestChange (полный набор), раз в день
        $schedule->exec("{$compilerWrapper} bestchange:files")
            ->daily()
            ->onOneServer()
            ->withoutOverlapping(180);
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

        // Bounded rates_history_logs retention (90d). Multiple daily runs drain backlog safely.
        $schedule->command('rates-history:prune --days=90 --batch=5000 --max-rows=50000 --sleep-ms=50')
            ->hourlyAt(20)
            ->onOneServer()
            ->withoutOverlapping(50);

        // Batch 11 / C.3C: 30-day session_attributions retention (attribution table only).
        $schedule->command('analytics:attribution-prune --days=30 --batch=500 --max-rows=5000 --sleep-ms=50')
            ->dailyAt('03:25')
            ->onOneServer()
            ->withoutOverlapping(50);

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
