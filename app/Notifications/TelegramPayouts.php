<?php

namespace App\Notifications;

use App\Models\WithdrawalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class TelegramPayouts extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(
        public mixed $tokens,
        public WithdrawalRequest $withdrawalRequest
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
        $content = view('telegram.payouts.message', [
            'detail' => $this->withdrawalRequest,
        ])->render();

        return TelegramMessage::create()
            ->token($this->tokens->token_access)
            ->to($this->tokens->id_channel)
            ->content($content)
            ->options([
                'parse_mode' => 'HTML',
            ]);
    }
}
