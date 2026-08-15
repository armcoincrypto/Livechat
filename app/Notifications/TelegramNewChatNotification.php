<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class TelegramNewChatNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $message = null;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(?string $message = null)
    {
        $this->message = $message;
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
     * @param  string  $message
     * @return TelegramMessage
     */
    public function toTelegram(Task $item)
    {
        $telegram = TelegramMessage::create()
            ->token(iEXSetting('telegram_bot_notice_token'))
            ->to(iEXSetting('telegram_bot_channel_id'));

        $telegram->content(view('telegram.new_chat', [
            'detail' => $item,
            'message' => $this->message,
        ])->render());

        return $telegram->options([
            'parse_mode' => 'HTML',
        ]);
    }
}
