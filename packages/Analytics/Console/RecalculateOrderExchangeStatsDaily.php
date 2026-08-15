<?php

namespace iEXPackages\Analytics\Console;

use App\Models\OrderExchangeStatDaily;
use App\Models\Task;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class RecalculateOrderExchangeStatsDaily extends Command
{
    /**
     * Примеры:
     *  php artisan stats:orders-daily
     *  php artisan stats:orders-daily --date=2025-11-22
     *  php artisan stats:orders-daily --from=2025-01-01 --to=2025-11-22
     *  php artisan stats:orders-daily --init-history
     */
    protected $signature = 'stats:orders-daily
        {--date= : Конкретная дата (Y-m-d)}
        {--from= : Начало диапазона (Y-m-d)}
        {--to= : Конец диапазона (Y-m-d)}
        {--force : Пересчитать и перезаписать существующие дни}
        {--init-history : Инициализировать статистику по всей истории заявок}';

    protected $description = 'Пересчитывает дневную статистику заявок обмена (tasks)';

    public function handle(): int
    {
        $tz = Config::get('exchange_stats.timezone', config('app.timezone', 'UTC'));

        $dateOption       = $this->option('date');
        $fromOption       = $this->option('from');
        $toOption         = $this->option('to');
        $force            = (bool) $this->option('force');
        $initWholeHistory = (bool) $this->option('init-history');

        // Если явно переданы date/from/to — работаем строго по ним
        if ($dateOption || ($fromOption && $toOption)) {
            [$from, $to] = $this->resolveDateRange($tz, $dateOption, $fromOption, $toOption);
        } else {
            // Никаких дат не передано. Решаем, что делать:

            if ($initWholeHistory) {
                // Прямая команда инициализации всей истории
                [$from, $to] = $this->resolveFullHistoryRange($tz);
                if (! $from || ! $to) {
                    $this->warn('В таблице tasks нет заявок, инициализировать статистику нечего.');
                    return self::SUCCESS;
                }

                $this->info(sprintf(
                    'Инициализация статистики по всей истории заявок: с %s по %s (TZ: %s)',
                    $from->toDateString(),
                    $to->toDateString(),
                    $tz
                ));
            } else {
                // Проверяем, есть ли уже статистика
                $statsExists = OrderExchangeStatDaily::query()->exists();

                if (! $statsExists) {
                    // Таблица пуста → автоматически запускаем init-history
                    [$from, $to] = $this->resolveFullHistoryRange($tz);
                    if (! $from || ! $to) {
                        $this->warn('В таблице tasks нет заявок, инициализировать статистику нечего.');
                        return self::SUCCESS;
                    }

                    $this->info(sprintf(
                        'Таблица order_exchange_stats_daily пуста. ' .
                        'Автоматическая инициализация истории с %s по %s (TZ: %s)',
                        $from->toDateString(),
                        $to->toDateString(),
                        $tz
                    ));
                } else {
                    // Обычный режим: если статистика уже есть — считаем только вчера/сегодня
                    [$from, $to] = $this->resolveDateRange($tz, $dateOption, $fromOption, $toOption);
                }
            }
        }

        $period = CarbonPeriod::create($from, $to);

        $this->info(sprintf(
            'Пересчитываем статистику заявок (tasks) с %s по %s (TZ: %s)',
            $from->toDateString(),
            $to->toDateString(),
            $tz
        ));

        $statusMap          = Config::get('exchange_stats.status_map', []);
        $completedStatuses  = $statusMap['completed'] ?? [];
        $rejectedStatuses   = $statusMap['rejected'] ?? [];
        $processingStatuses = $statusMap['processing'] ?? [];

        foreach ($period as $date) {
            /** @var Carbon $date */
            $this->line('→ День: ' . $date->toDateString());

            $dayStart = $date->copy()->startOfDay();
            $dayEnd   = $date->copy()->endOfDay();

            // created_at обычно хранится в UTC
            $dayStartUtc = $dayStart->copy()->setTimezone('UTC');
            $dayEndUtc   = $dayEnd->copy()->setTimezone('UTC');

            $stats = Task::query()
                ->whereBetween('created_at', [$dayStartUtc, $dayEndUtc])
                ->selectRaw('COUNT(*) as total_count')
                ->selectRaw('SUM(CASE WHEN status IN (' . $this->placeholders($completedStatuses) . ') THEN 1 ELSE 0 END) as completed_count')
                ->selectRaw('SUM(CASE WHEN status IN (' . $this->placeholders($rejectedStatuses) . ') THEN 1 ELSE 0 END) as rejected_count')
                ->selectRaw('SUM(CASE WHEN status IN (' . $this->placeholders($processingStatuses) . ') THEN 1 ELSE 0 END) as processing_count')
                ->addBinding($completedStatuses, 'select')
                ->addBinding($rejectedStatuses, 'select')
                ->addBinding($processingStatuses, 'select')
                ->first();

            $totalCount      = (int) ($stats->total_count ?? 0);
            $completedCount  = (int) ($stats->completed_count ?? 0);
            $rejectedCount   = (int) ($stats->rejected_count ?? 0);
            $processingCount = (int) ($stats->processing_count ?? 0);

            if ($force) {
                OrderExchangeStatDaily::query()
                    ->whereDate('date', $date->toDateString())
                    ->delete();
            }

            if (! $force && $totalCount === 0) {
                // День пустой — всё равно храним строку, чтобы статистика была полной
                OrderExchangeStatDaily::query()->updateOrCreate(
                    ['date' => $date->toDateString()],
                    [
                        'total_count'      => 0,
                        'completed_count'  => 0,
                        'rejected_count'   => 0,
                        'processing_count' => 0,
                    ]
                );

                $this->line('   Нет заявок за день, статистика обнулена/обновлена.');
                continue;
            }

            OrderExchangeStatDaily::query()->updateOrCreate(
                ['date' => $date->toDateString()],
                [
                    'total_count'      => $totalCount,
                    'completed_count'  => $completedCount,
                    'rejected_count'   => $rejectedCount,
                    'processing_count' => $processingCount,
                ]
            );

            $this->line(sprintf(
                '   total=%d, completed=%d, rejected=%d, processing=%d',
                $totalCount,
                $completedCount,
                $rejectedCount,
                $processingCount
            ));
        }

        $this->info('Готово.');

        return self::SUCCESS;
    }

    /**
     * Определяет диапазон дат на основе переданных опций.
     * По умолчанию (когда нет параметров) — вчера и сегодня.
     */
    protected function resolveDateRange(string $tz, ?string $dateOption, ?string $fromOption, ?string $toOption): array
    {
        if ($dateOption) {
            $date = Carbon::parse($dateOption, $tz)->startOfDay();

            return [$date->copy(), $date->copy()];
        }

        if ($fromOption && $toOption) {
            $from = Carbon::parse($fromOption, $tz)->startOfDay();
            $to   = Carbon::parse($toOption, $tz)->startOfDay();

            if ($to->lt($from)) {
                [$from, $to] = [$to, $from];
            }

            return [$from, $to];
        }

        // По умолчанию: вчера и сегодня
        $today     = Carbon::now($tz)->startOfDay();
        $yesterday = $today->copy()->subDay();

        return [$yesterday, $today];
    }

    /**
     * Диапазон по всей истории заявок (от первой до сегодняшнего дня).
     *
     * @return array{0: \Carbon\Carbon|null, 1: \Carbon\Carbon|null}
     */
    protected function resolveFullHistoryRange(string $tz): array
    {
        $firstTaskDate = Task::query()->min('created_at');

        if (! $firstTaskDate) {
            return [null, null];
        }

        // created_at хранится в UTC → приводим к бизнес-TZ
        $from = Carbon::parse($firstTaskDate, 'UTC')->setTimezone($tz)->startOfDay();
        $to   = Carbon::now($tz)->startOfDay();

        return [$from, $to];
    }

    protected function placeholders(array $values): string
    {
        if (empty($values)) {
            // IN (NULL) → всегда false
            return 'NULL';
        }

        return implode(', ', array_fill(0, count($values), '?'));
    }
}
