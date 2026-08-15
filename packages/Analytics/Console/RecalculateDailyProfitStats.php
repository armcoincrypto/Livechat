<?php

namespace iEXPackages\Analytics\Console;

use App\Models\DailyProfitStat;
use App\Models\DirectionExchange;
use App\Models\OrderProfitResult;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecalculateDailyProfitStats extends Command
{
    protected $signature = 'profit:recalculate-daily
        {--date= : Конкретная дата в формате YYYY-MM-DD}
        {--from= : Дата начала диапазона YYYY-MM-DD}
        {--to= : Дата конца диапазона YYYY-MM-DD}
        {--recent=30 : Пересчитать последние N дней (по дате завершения заявки)}';

    protected $description = 'Пересчитывает дневную статистику прибыли по заявкам за указанную дату или диапазон дат';

    public function handle(): int
    {
        $dateOption   = $this->option('date');
        $fromOption   = $this->option('from');
        $toOption     = $this->option('to');
        $recentOption = $this->option('recent');

        if ($dateOption) {
            // Только одна дата
            $from = $to = Carbon::parse($dateOption)->toDateString();
        } elseif ($fromOption && $toOption) {
            // Явный диапазон
            $from = Carbon::parse($fromOption)->toDateString();
            $to   = Carbon::parse($toOption)->toDateString();
        } else {
            // По умолчанию — последние N дней, считая от вчера
            $to   = Carbon::today()->toDateString();
            $days = (int) $recentOption > 0 ? (int) $recentOption : 30;
            $from = Carbon::parse($to)->subDays($days - 1)->toDateString();
        }

        $start = Carbon::parse($from);
        $end   = Carbon::parse($to);

        if ($end->lessThan($start)) {
            $this->error('Некорректный диапазон дат: date_to < date_from');
            return self::FAILURE;
        }

        $this->info(sprintf(
            'Пересчёт daily_profit_stats за период %s – %s...',
            $start->toDateString(),
            $end->toDateString()
        ));

        $current = $start->clone();
        while ($current->lessThanOrEqualTo($end)) {
            $this->recalculateForDate($current->toDateString());
            $current->addDay();
        }

        $this->info('Статистика успешно пересчитана.');
        return self::SUCCESS;
    }

    /**
     * Пересчитывает статистику за конкретный день.
     *
     * Здесь мы привязываемся к дате завершения заявки (tasks.completed_at),
     * чтобы дневная прибыль соответствовала фактическому дню, когда деньги заработаны.
     */
    protected function recalculateForDate(string $statDate): void
    {
        $this->line("  → Дата {$statDate}");

        // Удаляем старые записи за эту дату
        DailyProfitStat::query()
            ->whereDate('stat_date', $statDate)
            ->delete();

        // Собираем агрегаты по OrderProfitResult + tasks
        $rows = OrderProfitResult::query()
            ->join('tasks', 'tasks.id', '=', 'order_profit_results.task_id')
            ->selectRaw('
                DATE(tasks.completed_at) as stat_date,
                tasks.id_direction_exchange as direction_id,
                COUNT(order_profit_results.id) as total_orders,
                SUM(order_profit_results.profit_amount_usd) as total_profit_usd,
                AVG(order_profit_results.profit_amount_usd) as avg_profit_usd,
                MIN(order_profit_results.profit_amount_usd) as min_profit_usd,
                MAX(order_profit_results.profit_amount_usd) as max_profit_usd
            ')
            ->whereNotNull('tasks.completed_at')
            ->whereDate('tasks.completed_at', $statDate)
            ->groupBy('stat_date', 'direction_id')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn("   Нет данных для {$statDate}, пропускаем.");
            return;
        }

        DB::beginTransaction();

        try {
            foreach ($rows as $row) {
                /** @var DirectionExchange|null $direction */
                $direction = DirectionExchange::find($row->direction_id);

                // Имя направления
                $directionName = $direction?->name
                    ?? ($direction?->tech_name
                        ?? (($direction?->currency1?->code_currency?->name ?? '—')
                            . ' → '
                            . ($direction?->currency2?->code_currency?->name ?? '—')));

                // Код валют и платёжных систем
                $currencyFromCode   = $direction->currency1->code_currency->name ?? null;
                $currencyToCode     = $direction->currency2->code_currency->name ?? null;
                $currencyFromSystem = $direction->currency1->payment->name ?? null;
                $currencyToSystem   = $direction->currency2->payment->name ?? null;

                DailyProfitStat::updateOrCreate(
                    [
                        'stat_date'    => $row->stat_date,
                        'direction_id' => $row->direction_id,
                    ],
                    [
                        'direction_name' => $directionName,
                        'currency_from'  => trim(($currencyFromSystem ?? '') . ' ' . ($currencyFromCode ?? '')),
                        'currency_to'    => trim(($currencyToSystem ?? '') . ' ' . ($currencyToCode ?? '')),

                        'total_orders'     => (int) $row->total_orders,
                        'total_profit_usd' => (string) $row->total_profit_usd,
                        'avg_profit_usd'   => (string) $row->avg_profit_usd,
                        'min_profit_usd'   => (string) $row->min_profit_usd,
                        'max_profit_usd'   => (string) $row->max_profit_usd,
                    ]
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('   Ошибка при пересчёте за ' . $statDate . ': ' . $e->getMessage());
        }
    }
}
