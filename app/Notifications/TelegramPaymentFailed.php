<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class TelegramPaymentFailed extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public mixed $tokens,
        public mixed $order,
        public string $error_text)
    {}

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
        $id = iEXSetting('client_id_type_for_order') == 1 ? $this->order->public_id : $this->order->id;
        $content = "При автовыплате по заявке №{$id} возникли ошибки. ".PHP_EOL;
        $content .= 'Текст ошибки: '.$this->error_text;

        return TelegramMessage::create()
            ->token($this->tokens->token_access)
            ->to($this->tokens->id_channel)
            ->content($content)
            ->options([
                'parse_mode' => 'HTML',
            ]);
    }
}
