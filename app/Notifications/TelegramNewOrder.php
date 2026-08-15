<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class TelegramNewOrder extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(
        public mixed $tokens,
        public mixed $order
    )
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array
     */
    public function via()
    {
        return [TelegramChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @return TelegramMessage
     *
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
