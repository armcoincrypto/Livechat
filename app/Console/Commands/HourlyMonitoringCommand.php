<?php

namespace App\Console\Commands;

use App\Models\ContestModel;
use App\Models\DirectionExchange;
use App\Models\RewardProgram;
use App\Models\Task;
use App\Models\User;
use App\Settings\DirectionConfig;
use Carbon\Carbon;
use Illuminate\Console\Command;


/***
 * @deprecated
*/
class HourlyMonitoringCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitoring:hourly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Часовой мониторинг данных';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Автоматическое удаление неоплаченных заявок (конфигурация через направление обмена)
        DirectionExchange::select('id', 'auto_del_order_status', 'auto_del_order_day', 'auto_del_order_hour', 'auto_del_order_minute')
            ->whereNotNull('auto_del_order_status')
            ->chunk(500, function ($direction_exchange) {
                foreach ($direction_exchange as $item) {

                    $time_clear = Carbon::now();
                    if ((int) $item->auto_del_order_day > 0) {
                        $time_clear = $time_clear->subDays((int) $item->auto_del_order_day);
                    }
                    if ((int) $item->auto_del_order_hour > 0) {
                        $time_clear = $time_clear->subHours((int) $item->auto_del_order_hour);
                    }
                    if ((int) $item->auto_del_order_minute > 0) {
                        $time_clear = $time_clear->subMinutes((int) $item->auto_del_order_minute);
                    }

                    // Проверяем заявки с этими статусами
                    Task::where('id_direction_exchange', '=', $item->id)
                        ->whereIn('status', $item->auto_del_order_status)
                        ->where('created_at', '<=', $time_clear)->chunk(100, function ($orders) {
                            foreach ($orders as $order) {
                                $this->comment('Удаление заявки #'.$order->id);
                                iex_order_status_log($order, $order->status, 11);
                                $order->update(['status' => 11]);
                            }
                        });
                }
            });

        /// Конфигурация неоплаченных заявок
        $unpaid = app(DirectionConfig::class);
        if ($unpaid->isUnpaidAutoDeleteEnabled()) {
            $statuses = $unpaid->unpaidOrderStatuses();
            if (!empty($statuses)) {
                $time_clear = Carbon::now();
                if ((int) $unpaid->unpaidTimeDay() > 0) {
                    // сохраняем поведение как раньше: day интерпретировался как часы
                    $time_clear = $time_clear->subHours((int) $unpaid->unpaidTimeDay());
                }
                if ((int) $unpaid->unpaidTimeHour() > 0) {
                    $time_clear = $time_clear->subHours((int) $unpaid->unpaidTimeHour());
                }
                if ((int) $unpaid->unpaidTimeMinute() > 0) {
                    $time_clear = $time_clear->subMinutes((int) $unpaid->unpaidTimeMinute());
                }

                Task::whereIn('status', $statuses)
                    ->where('created_at', '<=', $time_clear)
                    ->chunk(100, function ($orders) {
                        foreach ($orders as $order) {
                            $this->comment('Удаление заявки #' . $order->id);
                            $order->update(['status' => 11]);
                        }
                    });
            }
        }

        // Проверяем активные конкурсы
        $contest = ContestModel::where('status', '=', 1)->first();
        if (! empty($contest)) {
            $start_time = Carbon::now();
            $duration = Carbon::parse($contest->duration);
            $leadtime = 0;
            if ($duration->gt($start_time)) {
                $leadtime = $duration->diffInSeconds($start_time);
            }
            if ($leadtime == 0) {
                $contest->update(['status' => 2]);
            }
        }

        // Проверяем, и если не активеен Telegram, включаем
        if ((int) iEXSetting('is_enable_telegram_exchange') == 1 and iEXSetting('is_user_telegram_status') == 0) {
            $this->call('iex:telegram', ['bot' => 'user']);
        }

        // Проверяем, и если не активеен Telegram, включаем
        if (! empty(iEXSetting('telegram_bot_admin_token')) and iEXSetting('is_admin_telegram_status') == 0) {
            $this->call('iex:telegram', ['bot' => 'admin']);
        }

        // Архивация заявок
        if (iEXSetting('is_auto_archived')) {
            Task::where('is_archive', '=', 0)
                ->whereDate('created_at', '<=', Carbon::today()->subDays(iEXSetting('archived_days')))
                ->whereIn('status', explode(',', iEXSetting('archived_statuses')))
                ->chunk(100, function ($orders) {
                    foreach ($orders as $order) {
                        $order->update([
                            'is_archive' => 1,
                            'archived_at' => Carbon::now(),
                        ]);
                    }
                });
        }

        // Проверка временных прав пользователей
        User::whereNotNull('role_expired_at')->where('is_active_role', '=', 1)->where('role_expired_at', '<=', Carbon::now())
            ->chunk(100, function ($users) {
                foreach ($users as $user) {
                    \Log::info(
                        sprintf('Пользователь %s удален из группы %s', $user->name, $user->roles()->pluck('name')->implode(', '))
                    );
                    $user->update(['role_expired_at' => '']);
                    $user->roles()->detach();
                }
            });

        // Обновляем бонусную систему
        $rewardProgram = RewardProgram::all();
        if (count($rewardProgram) > 0) {
            foreach ($rewardProgram as $item) {
                User::where('id_reward_program', '<', $item->id)->where('order_total_exchanges', '>', $item->amount)->update([
                    'id_reward_program' => $item->id,
                ]);
            }
        }
    }
}
