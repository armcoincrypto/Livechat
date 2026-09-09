<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * First-time account setup after order auto-registration.
 * Reuses the Laravel password-reset token/URL contract (reset-credentials).
 */
class SetPasswordNotification extends Notification
{
    public string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    protected function setupUrl(object $notifiable): string
    {
        return config('app.frontend_url')
            . '/auth/reset-credentials/'
            . $this->token
            . '/?email='
            . urlencode($notifiable->getEmailForPasswordReset());
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->locale($notifiable->language ?? app()->getLocale());

        $url = $this->setupUrl($notifiable);
        $expireMinutes = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire');

        return (new MailMessage)
            ->subject(__('Создайте пароль для аккаунта'))
            ->greeting(__('Здравствуйте!'))
            ->line(__('Мы создали ваш аккаунт на Exswaping после оформления заявки.'))
            ->line(__('Чтобы входить в личный кабинет, задайте пароль по защищённой ссылке.'))
            ->action(__('Задать пароль'), $url)
            ->line(__('Эта ссылка будет доступна в течение :count минут.', ['count' => $expireMinutes]))
            ->line(__('Если вы не создавали заявку, просто проигнорируйте это письмо.'));
    }
}
