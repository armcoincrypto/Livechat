<?php

namespace iEXPackages\Analytics\Console;

use App\Models\User;
use iEXPackages\AuthAudit\Models\AuthEvent;
use App\Models\UserGlobalStatsDaily;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StatsUsersGlobalDailyCommand extends Command
{
    protected $signature = 'stats:users-global-daily
        {--date= : Дата в формате Y-m-d, по умолчанию вчера}';

    protected $description = 'Собирает глобальную ежедневную статистику по пользователям (user_global_stats_daily)';

    public function handle(): int
    {
        $dateOption = $this->option('date');
        $date = $dateOption
            ? Carbon::parse($dateOption)->startOfDay()
            : Carbon::yesterday()->startOfDay();

        $this->info('Сбор глобальной статистики пользователей за: ' . $date->toDateString());

        DB::beginTransaction();

        try {
            $stats = [
                'date' => $date->toDateString(),
            ];

            // 1) Всего пользователей на конец дня
            $stats['total_users'] = User::query()
                ->where('created_at', '<=', $date->copy()->endOfDay())
                ->count();

            // 2) Новые пользователи за день
            $stats['new_users'] = User::query()
                ->whereDate('created_at', $date->toDateString())
                ->count();

            // 3) Активные пользователи (логины или заявки)
            // В новой системе аудит-логов используем только успешные входы (login_success)
            $userIdsAuth = AuthEvent::query()
                ->where('event', '=', 'login_success')
                ->whereNotNull('user_id')
                ->whereDate('created_at', $date->toDateString())
                ->pluck('user_id')
                ->filter()
                ->unique()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $userIdsTasks = Task::query()
                ->whereNotNull('id_user')
                ->whereDate('created_at', $date->toDateString())
                ->pluck('id_user')
                ->filter()
                ->unique()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $activeUserIds = collect($userIdsAuth)
                ->merge($userIdsTasks)
                ->unique()
                ->values();

            $stats['active_users'] = $activeUserIds->count();

            // 4) Пользователи, у которых были заявки
            $stats['users_with_orders'] = Task::query()
                ->whereNotNull('id_user')
                ->whereDate('created_at', $date->toDateString())
                ->distinct('id_user')
                ->count('id_user');

            // 5) Заявки по статусам
            // ВАЖНО: подправь статусы под свои константы, если отличаются
            $STATUS_SUCCESS  = 4;
            $STATUS_FAILED   = 5;
            $STATUS_CANCELED = 6; // если такого нет — будет просто 0

            $ordersRow = Task::query()
                ->selectRaw('
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as successful_orders,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed_orders,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as canceled_orders
                ', [
                    $STATUS_SUCCESS,
                    $STATUS_FAILED,
                    $STATUS_CANCELED,
                ])
                ->whereDate('created_at', $date->toDateString())
                ->first();

            $stats['total_orders']      = (int) ($ordersRow->total_orders ?? 0);
            $stats['successful_orders'] = (int) ($ordersRow->successful_orders ?? 0);
            $stats['failed_orders']     = (int) ($ordersRow->failed_orders ?? 0);
            $stats['canceled_orders']   = (int) ($ordersRow->canceled_orders ?? 0);

            // 6) Объём в USD
            $volumeRow = Task::query()
                ->leftJoin('order_exchange_totals', 'tasks.id', '=', 'order_exchange_totals.id_task')
                ->whereDate('tasks.created_at', $date->toDateString())
                ->selectRaw('SUM(order_exchange_totals.exchange_usd) as total_volume_usd')
                ->first();

            $stats['total_volume_usd'] = (float) ($volumeRow->total_volume_usd ?? 0);

            // 7) Прибыль в USD (если есть поле profit_usd в tasks — подстрой под себя)
            // пока ставим 0, либо смело меняй на свои поля
            $stats['total_profit_usd'] = 0;

            // 8) Средние показатели
            $activeUsers = max(1, (int) $stats['active_users']);

            $stats['avg_orders_per_active_user'] = $stats['total_orders'] > 0
                ? $stats['total_orders'] / $activeUsers
                : 0;

            $stats['avg_volume_per_active_user'] = $stats['total_volume_usd'] > 0
                ? $stats['total_volume_usd'] / $activeUsers
                : 0;

            UserGlobalStatsDaily::updateOrCreate(
                ['date' => $stats['date']],
                $stats
            );

            DB::commit();

            $this->info('Готово. Строка за ' . $stats['date'] . ' обновлена.');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Ошибка: ' . $e->getMessage());
            report($e);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
