<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class ResetPasswordNotification extends Notification
{
    /**
     * The password reset token.
     *
     * @var string
     */
    public $token;

    /**
     * The callback that should be used to build the mail message.
     *
     * @var \Closure|null
     */
    public static $toMailCallback;

    /**
     * Create a notification instance.
     *
     * @param  string  $token
     * @return void
     */
    public function __construct($token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's channels.
     *
     * @param  mixed  $notifiable
     * @return array|string
     */
    public function via($notifiable)
    {
        return ['mail'];
    }


    /**
     * Генерирует URL для сброса пароля.
     */
    protected function resetUrl(object $notifiable): string
    {
        return config('app.frontend_url') . "/auth/reset-credentials/{$this->token}/?email=" . urlencode($notifiable->getEmailForPasswordReset());
    }

    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail(object $notifiable): MailMessage
    {
        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $this->token);
        }

        $this->locale($notifiable->language ?? app()->getLocale());

        $url = $this->resetUrl($notifiable);
        $expireMinutes = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire');

        return (new MailMessage)
            ->subject(__('Запрос на восстановление пароля'))
            ->greeting(__('Здравствуйте!'))
            ->line(__('Мы получили запрос на восстановление пароля для вашего аккаунта.'))
            ->action(__('Создать новый пароль'), $url)
            ->line(__('Эта ссылка будет доступна в течение :count минут.', ['count' => $expireMinutes]))
            ->line(__('Если вы не отправляли запрос на восстановление, просто проигнорируйте это письмо. Ваш аккаунт в безопасности.'));
    }


    /**
     * Set a callback that should be used when building the notification mail message.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public static function toMailUsing($callback)
    {
        static::$toMailCallback = $callback;
    }
}
