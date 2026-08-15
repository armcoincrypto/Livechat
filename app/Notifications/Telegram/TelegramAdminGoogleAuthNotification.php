<?php

namespace App\Notifications\Telegram;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;


class TelegramAdminGoogleAuthNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $message,
        public mixed $user,
        public mixed $tokens,
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
        $content = sprintf('[%s - %s] - %s', $this->user->id, $this->user->name, $this->message);

        $telegram = TelegramMessage::create()
            ->token($this->tokens->token_access)
            ->to($this->tokens->id_channel);

        return $telegram->content($content)
            ->options([
                'parse_mode' => 'HTML',
            ]);
    }
}

