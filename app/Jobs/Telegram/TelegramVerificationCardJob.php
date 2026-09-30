<?php

declare(strict_types=1);

namespace App\Jobs\Telegram;

use App\Jobs\Telegram\Concerns\TelegramNotificationReliability;
use App\Models\TelegramNotification;
use App\Models\VerificationCard;
use App\Notifications\TelegramVerificationCard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class TelegramVerificationCardJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use TelegramNotificationReliability;

    public int $uniqueFor = 300;

    protected VerificationCard $verification_card;

    public function __construct(VerificationCard $verificationCard)
    {
        $this->verification_card = $verificationCard;
    }

    public function uniqueId(): string
    {
        return 'telegram-verification-card:'.$this->verification_card->id;
    }

    public function handle(): void
    {
        $sentKey = $this->telegramSentKey('verification_card', $this->verification_card->id, 'verification_card');
        if ($this->telegramAlreadySent($sentKey)) {
            Log::info('telegram_verification_card_duplicate_skipped', [
                'verification_card_id' => $this->verification_card->id,
            ]);

            return;
        }

        $notifications = TelegramNotification::where('status', 1)->where('ext_params->is_order_verification_card', 1)->get();
        $anyOk = false;
        foreach ($notifications as $notification) {
            try {
                Notification::route('telegram', $notification->id_channel)
                    ->notifyNow(new TelegramVerificationCard($notification, $this->verification_card));
                $anyOk = true;
            } catch (Throwable $e) {
                $this->telegramFailPermanentOrRetry($e, [
                    'verification_card_id' => $this->verification_card->id,
                    'channel_id' => $notification->id,
                ]);

                return;
            }
        }

        if ($anyOk) {
            $this->telegramMarkSent($sentKey);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->telegramLogDead($exception, [
            'verification_card_id' => $this->verification_card->id ?? null,
            'event' => 'verification_card',
        ]);
    }
}
