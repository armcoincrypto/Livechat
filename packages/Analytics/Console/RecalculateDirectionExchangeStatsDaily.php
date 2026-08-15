<?php

namespace iEXPackages\Analytics\Console;

use App\Models\DirectionExchangeStatDaily;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecalculateDirectionExchangeStatsDaily extends Command
{
    /**
     * Пример:
     * php artisan stats:directions-daily
     * php artisan stats:directions-daily --from=2025-01-01 --to=2025-01-31
     */
    protected $signature = 'stats:directions-daily
        {--from= : Дата начала периода (Y-m-d)}
        {--to=   : Дата окончания периода (Y-m-d)}
        {--force-init : Не автоопределять период, использовать только from/to}';

    protected $description = 'Пересчитывает daily-статистику по направлениям (direction_exchange_stats_daily)';

    // Подстрой под свои статусы tasks
    private array $completedStatuses = [4];

// отклонена
    private array $rejectedStatuses  = [5];

// отмены / ошибки / отменена пользователем / недействительна / удалена / ошибка выплаты
    private array $cancelledStatuses = [6, 7, 10, 11, 14];
    public function handle(): int
    {
        $tz = config('app.timezone', 'Europe/Moscow');

        $tableIsEmpty = DirectionExchangeStatDaily::query()->count() === 0;

        $fromOption = $this->option('from');
        $toOption   = $this->option('to');

        if ($tableIsEmpty && !$fromOption && !$toOption && !$this->option('force-init')) {
            $minTaskDate = Task::query()
                ->whereNotNull('created_at')
                ->orderBy('created_at')
                ->value(DB::raw('DATE(created_at)'));

            if (!$minTaskDate) {
                $this->warn('В таблице tasks нет данных, пересчёт невозможен.');
                return self::SUCCESS;
            }

            $from = Carbon::parse($minTaskDate, $tz)->startOfDay();
            $to   = now($tz)->endOfDay();

            $this->info(sprintf(
                'Таблица direction_exchange_stats_daily пуста. ' .
                'Автоинициализация истории с %s по %s (TZ: %s)',
                $from->toDateString(),
                $to->toDateString(),
                $tz
            ));
        } else {
            $from = $fromOption
                ? Carbon::parse($fromOption, $tz)->startOfDay()
                : now($tz)->subDays(7)->startOfDay(); // по умолчанию последние 7 дней

            $to = $toOption
                ? Carbon::parse($toOption, $tz)->endOfDay()
                : now($tz)->endOfDay();
        }

        if ($from->gt($to)) {
            $this->error('from > to, проверь параметры.');
            return self::FAILURE;
        }

        $this->info(sprintf(
            'Пересчитываем статистику по направлениям с %s по %s (TZ: %s)',
            $from->toDateString(),
            $to->toDateString(),
            $tz
        ));

        $current = $from->copy();

        while ($current->lte($to)) {
            $date  = $current->toDateString();
            $start = $current->copy()->startOfDay();
            $end   = $current->copy()->endOfDay();

            $this->line('→ День: ' . $date);

            // Группируем заявки по направлению
            $rows = Task::query()
                ->selectRaw('
                    id_direction_exchange,
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN status IN (' . implode(',', $this->completedStatuses) . ') THEN 1 ELSE 0 END) as completed_orders,
                    SUM(CASE WHEN status IN (' . implode(',', $this->rejectedStatuses)  . ') THEN 1 ELSE 0 END) as rejected_orders,
                    SUM(CASE WHEN status IN (' . implode(',', $this->cancelledStatuses) . ') THEN 1 ELSE 0 END) as cancelled_orders,
                    SUM(CASE WHEN status NOT IN (' . implode(',', array_merge($this->completedStatuses, $this->rejectedStatuses, $this->cancelledStatuses)) . ') THEN 1 ELSE 0 END) as processing_orders,
                    COUNT(DISTINCT id_user) as unique_users
                ')
                ->whereBetween('created_at', [$start, $end])
                ->whereNotNull('id_direction_exchange')
                ->groupBy('id_direction_exchange')
                ->get();

            if ($rows->isEmpty()) {
                $this->line('   Нет заявок по направлениям, пропускаем день.');
                $current->addDay();
                continue;
            }

            DB::transaction(function () use ($rows, $date) {
                foreach ($rows as $row) {
                    $directionId = (int) $row->id_direction_exchange;
                    if ($directionId === 0) {
                        continue;
                    }

                    DirectionExchangeStatDaily::query()->updateOrCreate(
                        [
                            'stat_date'             => $date,
                            'direction_exchange_id' => $directionId,
                        ],
                        [
                            'total_orders'          => (int) $row->total_orders,
                            'completed_orders'      => (int) $row->completed_orders,
                            'rejected_orders'       => (int) $row->rejected_orders,
                            'cancelled_orders'      => (int) $row->cancelled_orders,
                            'processing_orders'     => (int) $row->processing_orders,
                            'unique_users'          => (int) $row->unique_users,
                            'new_users'             => 0,   // TODO: можно реализовать "новых" клиентов
                            'total_amount_from_usd' => '0', // TODO: заменить на реальные суммы
                            'total_amount_to_usd'   => '0',
                            'total_profit_usd'      => '0',
                        ]
                    );
                }
            });

            $current->addDay();
        }

        $this->info('Статистика по направлениям успешно пересчитана.');
        return self::SUCCESS;
    }
}
