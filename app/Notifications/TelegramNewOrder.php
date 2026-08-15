<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class TelegramNewOrder extends Notification implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

    public int $timeout = 20;

    public int $uniqueFor = 86400;

    public function __construct(
        public mixed $tokens,
        public mixed $order
    ) {
        $this->onQueue('high');
    }

    public function uniqueId(): string
    {
        $orderId = $this->order instanceof Task ? (string) $this->order->id : 'unknown';

        return 'telegram-new-order:'.$orderId;
    }

    public function via()
    {
        return [TelegramChannel::class];
    }

    /**
     * @throws \Throwable
     */
    public function toTelegram()
    {
        $telegram = TelegramMessage::create()
            ->token($this->tokens->token_access)
            ->to($this->tokens->id_channel);

        $telegram->content(view('telegram.message', [
            'detail' => $this->order,
        ])->render());

        return $telegram->options([
            'parse_mode' => 'HTML',
        ]);
    }
}
