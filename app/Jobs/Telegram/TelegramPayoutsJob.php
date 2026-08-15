<?php

namespace App\Jobs\Telegram;

use App\Models\TelegramNotification;
use App\Models\WithdrawalRequest;
use App\Notifications\TelegramPayouts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class TelegramPayoutsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Объявляем модель WithdrawalRequest
     *
     * @var WithdrawalRequest
     */
    protected WithdrawalRequest $withdrawalRequest;

    /**
     * Create a new job instance.
     */
    public function __construct(WithdrawalRequest $withdrawalRequest)
    {
        $this->withdrawalRequest = $withdrawalRequest;
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
        $notifications = TelegramNotification::where('status', 1)->where('ext_params->is_order_withdrawal', 1)->get();
        foreach ($notifications as $notification) {
            Notification::route('telegram', $notification->id_channel)
                ->notify(new TelegramPayouts($notification, $this->withdrawalRequest));
        }
    }
}
