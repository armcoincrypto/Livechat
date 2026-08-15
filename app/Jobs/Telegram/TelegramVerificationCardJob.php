<?php
declare(strict_types=1);

namespace App\Jobs\Telegram;

use App\Models\TelegramNotification;
use App\Models\VerificationCard;
use App\Notifications\TelegramVerificationCard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class TelegramVerificationCardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Детали карты
     *
     * @var VerificationCard
     */
    protected VerificationCard $verification_card;

    /**
     * Create a new job instance.
     */
    public function __construct(VerificationCard $verificationCard)
    {
        $this->verification_card = $verificationCard;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $notifications = TelegramNotification::where('status', 1)->where('ext_params->is_order_verification_card', 1)->get();
        foreach ($notifications as $notification) {
            Notification::route('telegram', $notification->id_channel)
                ->notify(new TelegramVerificationCard($notification, $this->verification_card));
        }
    }
}
