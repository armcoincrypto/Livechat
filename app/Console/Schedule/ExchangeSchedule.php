<?php

namespace App\Console\Schedule;

use Illuminate\Console\Scheduling\Schedule;

class ExchangeSchedule
{
    /**
     * Laravel withoutOverlapping TTL for compiler:bestchange (minutes).
     *
     * Fallback safety bound only — normal release is schedule:finish after the
     * background wrapper exits. Must stay above max observed BC wrapper hold
     * (~6–7m post-L1 including POST_BC) and preferably below the operator
     * rates cadence so a missed finish cannot skip the next due tick.
     */
    public const BESTCHANGE_WITHOUT_OVERLAPPING_MINUTES = 15;

    /**
     * Minutes after the rates-cadence boundary when BC is nominally admitted.
     * Delegates to BestchangeAdmissionPolicy (Wave2 recovery: +6m — past courses
     * P95/jitter and competitors/generate_prices :05; see policy). Live interim
     * +4 geometry (64e650) is superseded: overdue catch-up + non-BC yield required.
     */
    public const BESTCHANGE_ADMISSION_OFFSET_MINUTES = BestchangeAdmissionPolicy::ADMISSION_OFFSET_MINUTES;

    /**
     * Competitors-only cron minutes that must not start inside the BC admission
     * window. Documented for ops crontab alignment (3-57/2).
     */
    public const COMPETITORS_CRON_EXPRESSION = '3-57/2 * * * *';

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
        // Wave3: background so schedule:run is not pinned by recount duration.
        $schedule->command('order-recount:run --details')
            ->everyMinute()
            ->onOneServer()
            ->withoutOverlapping(50)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/order_recount_run.log'));



        // Автоматическое обновление карты сайта (каждый день)
        $schedule->command('update:sitemap')->daily();

        // Inbound payment detector (NOT outbound merchant webhooks). Serial
        // gateway HTTP over open auto-check tasks; often multi-minute.
        // Must NOT hold cron schedule-run.lock via FD inheritance — see
        // scripts/ops/run_schedule_run_once.sh (Wave3 lock hygiene).
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
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/autopay_queue.log'));

        // 2) Запускаем авто-выплаты по заявкам из очереди (16)
        $schedule->command('merchant:autopay-run --chunk=10 --limit=10')
            ->everyMinute()
            ->onOneServer()
            ->withoutOverlapping(10)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/autopay_run.log'));

        // Обновление кеша BestChange
        $schedule->command('bestchange:cache-update')->hourly();

        // Приём callback-хэшей от мерчантов
        //$schedule->command('callback:receive_hash')->everyMinute();

        // Обработка ожидающих выплат
        $schedule->command('pay:pending-withdrawal')
            ->everyMinute()
            ->withoutOverlapping(55)
            ->runInBackground();

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
        $schedule->command('presence:snapshot')
            ->everyMinute()
            ->withoutOverlapping(55)
            ->runInBackground();

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
     * Cron expression Laravel Scheduling uses for a given minute cadence.
     * Must match Event::mutexName() hashing (expression + normalized command).
     */
    public static function cronExpressionForMinutes(int $minutes): string
    {
        [$kind, $value] = self::resolveMinuteCadence($minutes);
        if ($kind === 'cron') {
            return $value;
        }

        return match ($value) {
            'everyMinute' => '* * * * *',
            'everyTwoMinutes' => '*/2 * * * *',
            'everyThreeMinutes' => '*/3 * * * *',
            'everyFourMinutes' => '*/4 * * * *',
            'everyFiveMinutes' => '*/5 * * * *',
            'everyTenMinutes' => '*/10 * * * *',
            'everyFifteenMinutes' => '*/15 * * * *',
            'everyThirtyMinutes' => '*/30 * * * *',
            'hourly' => '0 * * * *',
            default => '* * * * *',
        };
    }

    /**
     * Cron expression for a minute cadence shifted by a fixed offset.
     * Example: minutes=30, offset=4 → "4,34 * * * *".
     */
    public static function cronExpressionForMinutesWithOffset(int $minutes, int $offsetMinutes): string
    {
        $minutes = $minutes <= 0 ? 1 : $minutes;
        $offsetMinutes = max(0, min($offsetMinutes, max(0, $minutes - 1)));
        if ($offsetMinutes === 0) {
            return self::cronExpressionForMinutes($minutes);
        }

        $parts = [];
        for ($m = $offsetMinutes; $m < 60; $m += $minutes) {
            $parts[] = (string) $m;
        }
        if ($parts === []) {
            return self::cronExpressionForMinutes($minutes);
        }

        return implode(',', $parts).' * * * *';
    }

    /**
     * True when an actual compiler:bestchange run is active.
     *
     * H1 observation signal only — delegates to BestchangeCompilerBusySignal.
     * Does NOT flock bestchange-compiler.lock / change shared-lock concurrency.
     * Customer/payment jobs must never call this gate.
     */
    public static function bestchangeCompilerBusy(): bool
    {
        return BestchangeCompilerBusySignal::isBusy();
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
        $compilerWrapper = '/opt/exswaping-owned-ops/current/scripts/ops/run_backend_compiler.sh';
        // H1 host-contention isolation: defer noncritical CPU/DB heavies while an
        // actual compiler:bestchange process is active (not compiler:courses, which
        // shares bestchange-compiler.lock / owners/bestchange). Does not alter BC
        // flock / withoutOverlapping / runInBackground / FD hygiene.
        // POST_BC_XML_REFRESH still invokes scheme:files directly after BC completes.
        // Wave2 (P0 recovery): also yield while BC freshness is overdue so shared
        // flock holders cannot starve catch-up admission.
        $whenBcIdle = static function (): bool {
            if (self::bestchangeCompilerBusy()) {
                return false;
            }
            $age = BestchangeAdmissionPolicy::lastSuccessAgeSeconds();

            return ! BestchangeAdmissionPolicy::shouldDeferNonBc($age);
        };

        // Wave3: heavy compilers MUST runInBackground. Foreground pinned schedule:run.
        // Dual lock (BOTH_REQUIRED):
        //   1) Laravel withoutOverlapping — schedule dispatch mutex (argument is MINUTES;
        //      CacheEventMutex uses expiresAt*60). Hard single-writer is NOT this lock.
        //   2) run_backend_compiler flock (bestchange-compiler.lock) — hard single-writer
        //      across schedule + odd-minute competitors-only cron.
        // Wave1 (2026-10-25): register BC BEFORE courses (dispatch order).
        // Wave2 (P0 recovery on 64e650): everyMinute + BestchangeAdmissionPolicy —
        //   nominal offset +6m; overdue catch-up (≥900s) admits any free minute
        //   without extending the 1800s financial policy. withoutOverlapping TTL
        //   remains a FALLBACK only (15m > post-L1 hold, <30m cadence).
        $whenBcAdmit = static function () use ($ratesMinutes): bool {
            return BestchangeAdmissionPolicy::shouldAdmit(
                (int) now()->minute,
                BestchangeAdmissionPolicy::lastSuccessAgeSeconds(),
                $ratesMinutes
            );
        };
        $schedule->exec("{$compilerWrapper} compiler:bestchange")
            ->everyMinute()
            ->when($whenBcAdmit)
            ->onOneServer()
            ->withoutOverlapping(self::BESTCHANGE_WITHOUT_OVERLAPPING_MINUTES)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/compiler_bestchange.log'));

        $courses = $schedule->exec("{$compilerWrapper} compiler:courses --isUpdate=0")
            ->onOneServer()
            ->withoutOverlapping(120)
            ->runInBackground()
            ->when($whenBcIdle)
            ->appendOutputTo(storage_path('logs/compiler_courses.log'));
        self::applyMinuteCadence($courses, $ratesMinutes);

        // Close "silent skip" visibility: log due/dispatch/mutex/flock/heartbeat every minute.
        $schedule->command('rates:bestchange-dispatch-watch --format=json')
            ->everyMinute()
            ->onOneServer()
            ->withoutOverlapping(2)
            ->appendOutputTo(storage_path('logs/bestchange_dispatch_watch.log'));

        // ZELLEUSD outgoing: refresh course_value from USDTTRC20→dest after BC peer updates.
        // H1: defer while BC busy (rates freshness, not payment path).
        $zelle = $schedule->command('rates:zelleusd-usdt-preview --all --apply')
            ->onOneServer()
            ->withoutOverlapping(120)
            ->runInBackground()
            ->when($whenBcIdle)
            ->appendOutputTo(storage_path('logs/zelle_usdt_benchmark.log'));
        self::applyMinuteCadence($zelle, $ratesMinutes);

        // DERIVED_MARKET_BASELINE BASE refresh. Crypto legs ≈ 10m; CBR ≈ 30m.
        // After 2026-08-24 ownership-guard + staged first-sync: scheduled --apply.
        // Hard skip: bestchange_directions.status=1 (health cannot steal ownership),
        // Zelle outgoing, and |Δ|>25% safety cap.
        $schedule->command('directions:derived-baseline-refresh --apply')
            ->everyTenMinutes()
            ->onOneServer()
            ->withoutOverlapping(9)
            ->runInBackground()
            ->when($whenBcIdle)
            ->appendOutputTo(storage_path('logs/derived_baseline_refresh.log'));

        // Проверка и обновление файлов курсов / public XML exports
        // H1: scheduled scheme:files collides with BC on CPU (separate flock by design).
        // Skip schedule ticks while BC busy; POST_BC_XML_REFRESH still runs after BC.
        $scheme = $schedule->exec("{$compilerWrapper} scheme:files")
            ->onOneServer()
            ->withoutOverlapping(10)
            ->runInBackground()
            ->when($whenBcIdle);
        self::applyMinuteCadence($scheme, $exportMinutes);
        // Read-only rate pipeline health (non-zero exit on critical conditions).
        $schedule->command('rates:health --format=json')
            ->everyFiveMinutes()
            ->onOneServer()
            ->withoutOverlapping(5)
            ->when($whenBcIdle)
            ->appendOutputTo(storage_path('logs/rates_health.log'));

        // Ownership drift detector (read-only alerts; never auto-repairs rates).
        $schedule->command('rates:ownership-drift-health --format=json')
            ->everyFiveMinutes()
            ->onOneServer()
            ->withoutOverlapping(5)
            ->when($whenBcIdle)
            ->appendOutputTo(storage_path('logs/rates_ownership_drift.log'));

        // Owner-authority rate drift (read-only; never mutates economics).
        // Per-job withoutOverlapping only — no global flock. Must not block BC/merchant.
        $schedule->command('rates:authority-drift-monitor --format=summary')
            ->everyFiveMinutes()
            ->onOneServer()
            ->withoutOverlapping(8)
            ->runInBackground()
            ->when($whenBcIdle)
            ->appendOutputTo(storage_path('logs/rates_authority_drift_monitor.log'));

        $schedule->command('orders:waiting-deposit-health --format=json')
            ->everyFiveMinutes()
            ->onOneServer()
            ->withoutOverlapping(5)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/orders_waiting_deposit_health.log'));

        $schedule->command('orders:payment-routing-health --format=json')
            ->everyFiveMinutes()
            ->onOneServer()
            ->withoutOverlapping(5)
            ->appendOutputTo(storage_path('logs/orders_payment_routing_health.log'));

        // Fast market plane: shared BestChange snapshot refresh (no full compiler).
        // Gated by FAST_MARKET_PLANE_ENABLED (default false). Does not replace
        // compiler:bestchange reconciliation / catch-up (Phase 22).
        $schedule->command('rates:fast-market-snapshot-refresh --format=json')
            ->everyMinute()
            ->onOneServer()
            ->withoutOverlapping(2)
            ->runInBackground()
            ->when(static fn (): bool => \App\Services\Rates\FastMarket\FastMarketFlags::planeEnabled())
            ->appendOutputTo(storage_path('logs/fast_market_snapshot_refresh.log'));

        // Генерация минимальной и максимальной цены
        $schedule->exec("{$compilerWrapper} compiler:generate_prices")
            ->everyFiveMinutes()
            ->onOneServer()
            ->withoutOverlapping(10)
            ->runInBackground();

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

        // Bounded rates_history_logs retention (30d). Analytics uses ~24h; admin charts default 1d;
        // command safety floor is 30d. Live quotes do not read this table. Hourly runs drain safely.
        $schedule->command('rates-history:prune --days=30 --batch=5000 --max-rows=50000 --sleep-ms=50')
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

        // Partner monthly accrual — fail-closed; OFF until Wave D activation.
        if (filter_var(config('partner_accrual.scheduler_enabled'), FILTER_VALIDATE_BOOLEAN)
            && filter_var(config('partner_accrual.enabled'), FILTER_VALIDATE_BOOLEAN)
            && filter_var(config('partner_accrual.allow_apply'), FILTER_VALIDATE_BOOLEAN)
        ) {
            $schedule->command('partner-accrual:run --apply')
                ->dailyAt('00:15')
                ->timezone((string) config('partner_accrual.business_timezone', config('app.timezone', 'Europe/Moscow')))
                ->onOneServer()
                ->withoutOverlapping(50);
        }
    }
}
