<?php

namespace App\Notifications;

use App\Models\VerificationCard;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class TelegramVerificationCard extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(
        public mixed $tokens,
        public VerificationCard $verificationCard
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
     */
    public function toTelegram(): TelegramMessage
    {
        $content = '<b>Поступила заявка №'.$this->verificationCard->id.' на верификацию карты '.$this->verificationCard->currency->payment->name.'</b>';

        return TelegramMessage::create()
            ->token($this->tokens->token_access)
            ->to($this->tokens->id_channel)
            ->content($content)
            ->options([
                'parse_mode' => 'HTML',
            ]);
    }
}
