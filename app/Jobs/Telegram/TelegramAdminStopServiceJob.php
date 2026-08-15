<?php
declare(strict_types=1);

namespace App\Jobs\Telegram;

use App\Models\Task;
use App\Models\TelegramNotification;
use App\Notifications\Telegram\TelegramAdminStopServiceNotification;
use App\Notifications\TelegramPaymentFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class TelegramAdminStopServiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     */
    public function __construct()
    {
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
        $notifications = TelegramNotification::where('status', 1)->where('ext_params->is_send_statistics', 1)->get();
        foreach ($notifications as $notification)
        {
            Notification::route('telegram', $notification->id_channel)
                ->notify(new TelegramAdminStopServiceNotification($notification));
        }
    }
}
