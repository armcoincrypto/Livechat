<?php
declare(strict_types=1);

namespace App\Jobs\Telegram;

use App\Models\Task;
use App\Models\TelegramNotification;
use App\Notifications\TelegramAutoPaymentNotification;
use App\Notifications\TelegramPaymentFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class TelegramAutoPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Детали заявки
     *
     * @var Task
     */
    protected Task $order;

    /**
     * Провайдер
     *
     * @var string
     */
    protected string $provider;

    /**
     * Create a new job instance.
     */
    public function __construct(Task $order, string $provider)
    {
        $this->order = $order;
        $this->provider = $provider;
    }

    /**
     * Execute the job.
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function handle()
    {
        $notifications = TelegramNotification::where('status', 1)->where('ext_params->is_order_pay', 1)->get();
        foreach ($notifications as $notification) {
            Notification::route('telegram', $notification->id_channel)
                ->notify(new TelegramAutoPaymentNotification($notification, $this->order, $this->provider));
        }
    }
}
