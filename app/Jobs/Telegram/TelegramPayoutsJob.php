<?php

declare(strict_types=1);

namespace App\Jobs\Telegram;

use App\Jobs\Telegram\Concerns\TelegramNotificationReliability;
use App\Models\TelegramNotification;
use App\Models\WithdrawalRequest;
use App\Notifications\TelegramPayouts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class TelegramPayoutsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use TelegramNotificationReliability;

    public int $uniqueFor = 300;

    protected WithdrawalRequest $withdrawalRequest;

    public function __construct(WithdrawalRequest $withdrawalRequest)
    {
        $this->withdrawalRequest = $withdrawalRequest;
    }

    public function uniqueId(): string
    {
        return 'telegram-payouts:'.$this->withdrawalRequest->id;
    }

    public function handle(): void
    {
        $sentKey = $this->telegramSentKey('withdrawal', $this->withdrawalRequest->id, 'order_bonuses_withdrawal');
        if ($this->telegramAlreadySent($sentKey)) {
            Log::info('telegram_payouts_duplicate_skipped', [
                'withdrawal_id' => $this->withdrawalRequest->id,
            ]);

            return;
        }

        $notifications = TelegramNotification::where('status', 1)->where('ext_params->is_order_withdrawal', 1)->get();
        $anyOk = false;
        foreach ($notifications as $notification) {
            try {
                Notification::route('telegram', $notification->id_channel)
                    ->notifyNow(new TelegramPayouts($notification, $this->withdrawalRequest));
                $anyOk = true;
            } catch (Throwable $e) {
                $this->telegramFailPermanentOrRetry($e, [
                    'withdrawal_id' => $this->withdrawalRequest->id,
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
            'withdrawal_id' => $this->withdrawalRequest->id ?? null,
            'event' => 'order_bonuses_withdrawal',
        ]);
    }
}
