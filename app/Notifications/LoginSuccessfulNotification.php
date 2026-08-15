<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginSuccessfulNotification extends Notification
{
    use Queueable;

    /**
     * Местоположение
     */
    protected ?string $location;

    /**
     * Create a new notification instance.
     */
    public function __construct(?string $location = null)
    {
        $this->location = $location;
        $this->locale = $locale ?? app()->getLocale();
    }

    /**
     * Получите каналы уведомления.
     *
     * @param  mixed  $notifiable
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Постройте почтовое представление уведомления.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = __('Вход в аккаунт прошёл успешно');

        return (new MailMessage)->subject($subject)
            ->markdown('emails.notification.login_successful', [
                    'subject' => $subject,
                    'user' => $notifiable,
                    'geo_string' => $this->location ?? __('Неизвестное местоположение', locale: $this->locale),
                    'sitename' => iEXContentLanguage('sitename', $this->locale),
                ]
            );
    }
}
