<?php

declare(strict_types=1);

namespace App\Jobs\Telegram;

use App\Models\KycProviderSession;
use App\Models\User;
use App\Notifications\TelegramIdentityVerified;
use App\Services\TelegramOperator\TelegramOperatorAuthService;
use App\Services\TelegramOperator\TelegramSecretRedactor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

/**
 * Private KYC approval notice.
 *
 * Sender is TELEGRAM_OPERATOR_BOT_TOKEN (@exswapingnotyfbot).
 * Destinations are linked operator private chats. The channel notification row is not used.
 * One failed destination does not clear KYC approval. Successful destinations are
 * not resent when another destination retries (exactly-once per approval × chat).
 */
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
        return 'telegram-identity-verified:'.$this->userId.':'.$this->sessionPk;
    }

    public function handle(TelegramOperatorAuthService $auth): void
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

        $chatIds = $auth->authorizedActionTelegramUserIds();
        if ($chatIds === []) {
            Log::warning('telegram_identity_verified_no_operator', [
                'user_id' => $this->userId,
                'session_pk' => $this->sessionPk,
            ]);

            return;
        }

        foreach ($chatIds as $chatId) {
            $chat = (string) $chatId;
            $sentKey = $this->sentCacheKey($chat);
            if (Cache::get($sentKey)) {
                Log::info('telegram_identity_verified_destination_skipped', [
                    'user_id' => $this->userId,
                    'session_pk' => $this->sessionPk,
                    'chat_id_suffix' => substr($chat, -3),
                ]);

                continue;
            }

            try {
                Notification::route('telegram', $chat)
                    ->notifyNow(new TelegramIdentityVerified($user, $session, $chat));
                Cache::put($sentKey, 1, now()->addDay());
                Log::info('telegram_identity_verified_sent', [
                    'user_id' => $this->userId,
                    'session_pk' => $this->sessionPk,
                    'destination' => 'operator_private_chat',
                    'chat_id_suffix' => substr($chat, -3),
                ]);
            } catch (Throwable $e) {
                $safe = TelegramSecretRedactor::message($e->getMessage());
                Log::error('telegram_identity_verified_failed', [
                    'user_id' => $this->userId,
                    'session_pk' => $this->sessionPk,
                    'destination' => 'operator_private_chat',
                    'chat_id_suffix' => substr($chat, -3),
                    'error' => $safe,
                ]);
                throw new RuntimeException('Telegram identity notice failed: '.$safe);
            }
        }
    }

    public function sentCacheKey(string $chatId): string
    {
        return 'telegram_identity_verified_sent:'.$this->userId.':'.$this->sessionPk.':'.$chatId;
    }
}
