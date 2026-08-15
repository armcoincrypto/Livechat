<?php

namespace iEXPackages\Analytics\Console;

use App\Models\Currency;
use App\Models\CurrencyAnalyticsDaily;
use App\Models\Task;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;

class RebuildCurrencyAnalyticsDailyCommand extends Command
{
    protected $signature = 'stats:currencies-daily
        {--from= : Дата начала в формате YYYY-MM-DD}
        {--to=   : Дата конца в формате YYYY-MM-DD}';

    protected $description = 'Пересчитывает ежедневную статистику по валютам (currencies_analytics_daily)';

    public function handle(): int
    {
        $this->info('Пересчитываем статистику по валютам (currencies_analytics_daily)');

        $fromInput = $this->option('from');
        $toInput   = $this->option('to');

        if ($fromInput && $toInput) {
            $from = Carbon::createFromFormat('Y-m-d', $fromInput)->startOfDay();
            $to   = Carbon::createFromFormat('Y-m-d', $toInput)->endOfDay();
        } else {
            $minDate = Task::whereNotNull('completed_at')->min('completed_at');

            if (! $minDate) {
                $this->warn('Нет завершённых заявок, пересчитывать нечего.');
                return self::SUCCESS;
            }

            $from = Carbon::parse($minDate)->startOfDay();
            $to   = now()->endOfDay();
        }

        $this->line(sprintf(
            'Период: %s — %s',
            $from->toDateString(),
            $to->toDateString()
        ));

        $period = CarbonPeriod::create($from, $to);

        foreach ($period as $date) {
            $this->rebuildDay($date);
        }

        $this->info('Статистика по валютам успешно пересчитана.');
        return self::SUCCESS;
    }

    protected function rebuildDay(Carbon $date): void
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd   = $date->copy()->endOfDay();

        $this->line('→ День: '.$date->toDateString());

        // Чистим старые записи за день
        CurrencyAnalyticsDaily::whereDate('date', $date->toDateString())->delete();

        // Базовый запрос по заявкам
        $tasksQuery = Task::query()
            ->join('direction_exchange', 'direction_exchange.id', '=', 'tasks.id_direction_exchange')
            ->whereBetween('tasks.completed_at', [$dayStart, $dayEnd])
            ->where('tasks.status', 4); // подставь свой статус "завершена"

        if (! $tasksQuery->exists()) {
            $this->line('   Нет завершённых заявок за день, статистика обнулена/обновлена.');
            return;
        }

        // Агрегация по currency1 (Отдаю)
        $inRows = $tasksQuery->cloneWithout(['columns', 'orders'])
            ->selectRaw('direction_exchange.id_currency1 as id_currency')
            ->selectRaw('SUM(tasks.give_price) as sum_in')   // ПОДСТАВЬ свои поля
            ->selectRaw('COUNT(*) as cnt_in')
            ->groupBy('direction_exchange.id_currency1')
            ->get();

        // Агрегация по currency2 (Получаю)
        $outRows = $tasksQuery->cloneWithout(['columns', 'orders'])
            ->selectRaw('direction_exchange.id_currency2 as id_currency')
            ->selectRaw('SUM(tasks.receiving_price) as sum_out') // ПОДСТАВЬ свои поля
            ->selectRaw('COUNT(*) as cnt_out')
            ->groupBy('direction_exchange.id_currency2')
            ->get();

        // Собираем по валютам
        $byCurrency = [];

        foreach ($inRows as $row) {
            $idCurrency = (int) $row->id_currency;

            $byCurrency[$idCurrency] ??= [
                'in_amount'      => BigDecimal::zero(),
                'out_amount'     => BigDecimal::zero(),
                'in_count'       => 0,
                'out_count'      => 0,
                'in_amount_usd'  => BigDecimal::zero(),
                'out_amount_usd' => BigDecimal::zero(),
            ];

            $byCurrency[$idCurrency]['in_amount'] =
                $byCurrency[$idCurrency]['in_amount']->plus((string) $row->sum_in);
            $byCurrency[$idCurrency]['in_count']  += (int) $row->cnt_in;
        }

        foreach ($outRows as $row) {
            $idCurrency = (int) $row->id_currency;

            $byCurrency[$idCurrency] ??= [
                'in_amount'      => BigDecimal::zero(),
                'out_amount'     => BigDecimal::zero(),
                'in_count'       => 0,
                'out_count'      => 0,
                'in_amount_usd'  => BigDecimal::zero(),
                'out_amount_usd' => BigDecimal::zero(),
            ];

            $byCurrency[$idCurrency]['out_amount'] =
                $byCurrency[$idCurrency]['out_amount']->plus((string) $row->sum_out);
            $byCurrency[$idCurrency]['out_count']  += (int) $row->cnt_out;
        }

        // Теперь конвертация в USD по агрегированной сумме за день
        foreach ($byCurrency as $idCurrency => $data) {
            /** @var Currency|null $currency */
            $currency = Currency::with('code_currency')->find($idCurrency);
            if (! $currency || ! $currency->code_currency) {
                continue;
            }

            $code = $currency->code_currency->name;

            $inUsd  = BigDecimal::zero();
            $outUsd = BigDecimal::zero();

            if (! $data['in_amount']->isZero()) {
                $inUsd = BigDecimal::of(
                    (string) calculator_converter($code, 'USD', (string) $data['in_amount'])
                );
            }

            if (! $data['out_amount']->isZero()) {
                $outUsd = BigDecimal::of(
                    (string) calculator_converter($code, 'USD', (string) $data['out_amount'])
                );
            }

            CurrencyAnalyticsDaily::create([
                'date'           => $dayStart->toDateString(),
                'id_currency'    => $idCurrency,
                'in_amount'      => (string) $data['in_amount'],
                'out_amount'     => (string) $data['out_amount'],
                'in_count'       => $data['in_count'],
                'out_count'      => $data['out_count'],
                'in_amount_usd'  => (string) $inUsd,
                'out_amount_usd' => (string) $outUsd,
            ]);
        }

        $this->line('   Готово, валют за день: '.count($byCurrency));
    }
}
