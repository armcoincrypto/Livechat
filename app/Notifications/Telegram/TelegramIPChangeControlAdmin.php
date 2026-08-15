<?php

namespace App\Notifications\Telegram;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;


class TelegramIPChangeControlAdmin extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
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
        $content = sprintf('У пользователя (%s-%s), который имеет доступ к админ-панели изменился внезапно IP Адрес. (Было: %s, Стало: %s)',
            $this->user->id, $this->user->name,
            $this->user->logged_ip_address, $this->user->ip_address
        );

        $telegram = TelegramMessage::create()
            ->token($this->tokens->token_access)
            ->to($this->tokens->id_channel);

        return $telegram->content($content)
            ->options([
                'parse_mode' => 'HTML',
            ]);
    }
}
