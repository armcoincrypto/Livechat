<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class TelegramOrderPostponedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
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
    public function toTelegram(Task $item)
    {
        $content = 'Заявка №'.iEXSetting('client_id_type_for_order') == 1 ? $item->public_id : $item->id.' по неизвестной причине была отложена';

        return TelegramMessage::create()
            ->token(iEXSetting('telegram_bot_notice_token'))
            ->to(iEXSetting('telegram_bot_channel_id'))
            ->content($content)
            ->options([
                'parse_mode' => 'HTML',
            ]);
    }
}
