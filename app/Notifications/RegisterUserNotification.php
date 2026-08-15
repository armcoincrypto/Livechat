<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegisterUserNotification extends Notification
{
    use Queueable;

    /**
     * Открытый пароль
     */
    protected string $password;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $password, ?string $locale = null)
    {
        $this->password = $password;
        $this->locale($locale ?? app()->getLocale());
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array
     */
    public function via(object $notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = __('Добро пожаловать! Вы успешно зарегистрированы', [], $this->locale);

        return (new MailMessage)
            ->subject($subject)
            ->markdown(
                'emails.notification.new_user', [
                    'locale' => $this->locale,
                    'subject' => $subject,
                    'user' => $notifiable,
                    'password' => $this->password,
                    'sitename' => iEXContentLanguage('sitename', $this->locale),
                ]
            );
    }
}
