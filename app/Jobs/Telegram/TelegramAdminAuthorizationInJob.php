<?php
declare(strict_types=1);

namespace App\Jobs\Telegram;

use App\Models\TelegramNotification;
use App\Models\User;
use App\Notifications\Telegram\TelegramAdminAuthorization;
use App\Notifications\Telegram\TelegramAdminGoogleAuthNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class TelegramAdminAuthorizationInJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $notifications = TelegramNotification::where('status', 1)->where('ext_params->is_allowed_admin', 1)->get();
            foreach ($notifications as $notification) {
                Notification::route('telegram', $notification->id_channel)
                    ->notify(new TelegramAdminAuthorization($notification, $this->user));
            }

        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
        }
    }
}
