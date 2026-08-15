<?php

namespace App\Jobs\Telegram;

use App\Models\TelegramNotification;
use App\Models\User;
use App\Notifications\Telegram\TelegramAdminGoogleAuthNotification;
use App\Notifications\TelegramAutoPaymentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class TelegramAdminTextActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public string $message,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $notifications = TelegramNotification::where('status', 1)->where('ext_params->is_google2fa', 1)->get();
            foreach ($notifications as $notification) {
                Notification::route('telegram', $notification->id_channel)
                    ->notify(new TelegramAdminGoogleAuthNotification($this->message, $this->user, $notification));
            }

        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
        }
    }
}
