<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class TelegramAutoPaymentNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public mixed $tokens,
        public Task $order,
        public string $provider,
    ) {}

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
        $price = $this->order->receiving_price.' '.$this->order->direction_exchange->currency2->code_currency->name;
        $content = '['.$this->provider.'] - автовыплата по заявке №'.$this->order->id.' на сумму '.$price.' успешно завершена.';

        return TelegramMessage::create()
            ->token($this->tokens->token_access)
            ->to($this->tokens->id_channel)
            ->content($content)
            ->options([
                'parse_mode' => 'HTML',
            ]);
    }
}
