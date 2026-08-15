<?php

namespace App\Notifications\Telegram;

use App\Models\TelegramNotification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;


class TelegramAdminAuthorization extends Notification implements ShouldQueue
{
    use Queueable;

    protected $telegramNotification;


    /**
     * Create a new notification instance.
     */
    public function __construct(
        TelegramNotification $telegramNotification,
        public mixed $user,
    )
    {
        $this->telegramNotification = $telegramNotification;
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
        $content = sprintf('[%s - %s] - Авторизовался в панели администрирования', $this->user->id, $this->user->name);

        return TelegramMessage::create()
            ->token($this->telegramNotification->token_access)
            ->to($this->telegramNotification->id_channel)
            ->content($content)
            ->options([
                'parse_mode' => 'HTML',
            ]);
    }
}

