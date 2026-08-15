<?php
namespace App\Notifications\Telegram;

use App\Models\Task;
use App\Models\TaskConvertLog;
use App\Models\TelegramNotification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;


class TelegramAdminStopServiceNotification extends Notification implements ShouldQueue
{
    use Queueable;


    /**
     * Create a new notification instance.
     */
    public function __construct(
        public mixed $tokens)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array
     */
    public function via()
    {
        return [TelegramChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @return TelegramMessage
     *
     * @throws \Throwable
     */
    public function toTelegram()
    {
        $commonAmount = TaskConvertLog::whereDate('created_at', '=', Carbon::today());
        //Сумма обменов за сегодня
        $amount_exchanges = 0;
        if ($commonAmount->count() > 0) {
            foreach ($commonAmount->get() as $item) {
                $amount_exchanges += $item->to_usd;
            }
        }

        $today_order = Task::whereDate('created_at', '=', Carbon::today())->count();
        $order_success = Task::where('status', '=', 4)->whereDate('created_at', '=', Carbon::today())->count();
        $order_waiting = Task::where('status', '=', 3)->whereDate('created_at', '=', Carbon::today())->count();
        $order_cancel = Task::where('status', '=', 5)->whereDate('created_at', '=', Carbon::today())->count();
        $new_users = User::whereDate('created_at', Carbon::today())->count();

        return TelegramMessage::create()
            ->token($this->tokens->token_access)
            ->to($this->tokens->id_channel)
            ->content(
                view("telegram.stop_service", [
                    'options' => [
                        'today_order' => $today_order,
                        'order_success' => $order_success,
                        'order_waiting' => $order_waiting,
                        'cancel_order' => $order_cancel,
                        'new_users' => $new_users,
                        'amount_exchanges' => $amount_exchanges,
                    ]
                ])->render()
            )
            ->options([
                'parse_mode' => 'HTML',
            ]);
    }
}
