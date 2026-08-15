<?php

namespace App\Jobs\Telegram;

use App\Models\Task;
use App\Models\TelegramNotification;
use App\Notifications\TelegramNewOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class TelegramOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Детали заявки
     *
     * @var Task
     */
    protected Task $order;

    /**
     * Create a new job instance.
     */
    public function __construct(Task $order, public string $param)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function handle(): void
    {
        $notifications = TelegramNotification::where('status', '=', 1)->where('ext_params->'. $this->param, 1)->get();
        foreach ($notifications as $notification) {
            Notification::route('telegram', $notification->id_channel)
                ->notify(new TelegramNewOrder($notification, $this->order));
        }
    }
}
