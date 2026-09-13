<?php

declare(strict_types=1);

namespace App\Jobs\Telegram;

use App\Models\KycProviderSession;
use App\Models\TelegramNotification;
use App\Models\User;
use App\Notifications\TelegramIdentityVerified;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class TelegramIdentityVerifiedJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

    public int $timeout = 20;

    public int $uniqueFor = 86400;

    public function __construct(
        public int $userId,
        public int $sessionPk,
    ) {
        $this->onQueue('high');
    }

    public function uniqueId(): string
    {
        return 'telegram-identity-verified:'.$this->userId;
    }

    public function handle(): void
    {
        $user = User::query()->find($this->userId);
        $session = KycProviderSession::query()->find($this->sessionPk);
        if (!$user || !$session) {
            Log::warning('telegram_identity_verified_missing_models', [
                'user_id' => $this->userId,
                'session_pk' => $this->sessionPk,
            ]);

            return;
        }

        $notifications = TelegramNotification::query()->where('status', 1)->get();
        if ($notifications->isEmpty()) {
            Log::warning('telegram_identity_verified_no_channel', [
                'user_id' => $this->userId,
                'session_pk' => $this->sessionPk,
            ]);

            return;
        }

        foreach ($notifications as $notification) {
            $channelChat = (string) $notification->id_channel;
            if ($channelChat === '') {
                continue;
            }
            try {
                Notification::route('telegram', $channelChat)
                    ->notifyNow(new TelegramIdentityVerified(
                        $notification,
                        $user,
                        $session,
                        $channelChat,
                    ));
                Log::info('telegram_identity_verified_sent', [
                    'user_id' => $this->userId,
                    'session_pk' => $this->sessionPk,
                    'channel_id' => $notification->id,
                ]);
            } catch (Throwable $e) {
                Log::error('telegram_identity_verified_failed', [
                    'user_id' => $this->userId,
                    'session_pk' => $this->sessionPk,
                    'channel_id' => $notification->id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }
    }
}
