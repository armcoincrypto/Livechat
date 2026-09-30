<?php

declare(strict_types=1);

namespace App\Jobs\Telegram;

use App\Jobs\Telegram\Concerns\TelegramNotificationReliability;
use App\Models\Task;
use App\Notifications\TelegramConfirmBlockchain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramConfirmBlockchainJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use TelegramNotificationReliability;

    public int $uniqueFor = 300;

    protected Task $order;

    protected string $provider;

    public function __construct(Task $order, string $provider)
    {
        $this->order = $order;
        $this->provider = $provider;
    }

    public function uniqueId(): string
    {
        return 'telegram-confirm-blockchain:'.$this->order->id.':'.$this->provider;
    }

    public function handle(): void
    {
        $sentKey = $this->telegramSentKey('order', $this->order->id, 'first_confirm_blockchain:'.$this->provider);
        if ($this->telegramAlreadySent($sentKey)) {
            Log::info('telegram_confirm_blockchain_duplicate_skipped', [
                'order_id' => $this->order->id,
                'provider' => $this->provider,
            ]);

            return;
        }

        try {
            // notifyNow keeps retry ownership on this job (no nested ShouldQueue hop).
            $this->order->notifyNow(new TelegramConfirmBlockchain($this->provider));
            $this->telegramMarkSent($sentKey);
        } catch (Throwable $e) {
            $this->telegramFailPermanentOrRetry($e, [
                'order_id' => $this->order->id,
                'provider' => $this->provider,
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->telegramLogDead($exception, [
            'order_id' => $this->order->id ?? null,
            'provider' => $this->provider ?? null,
            'event' => 'first_confirm_blockchain',
        ]);
    }
}
