<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmail extends Notification
{
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
     *
     * @param  mixed  $notifiable
     */
    public function toMail(object $notifiable): MailMessage
    {
        $this->locale($notifiable->language ?? 'ru');
        $verificationUrl = $this->verificationUrl($notifiable);

        // Получаем информацию о пользователя
        $subject = __('Подтверждение регистрации', [], $this->locale);

        return (new MailMessage)->subject($subject)
            ->markdown(
                'emails.notification.verify_email', [
                    'verify' => $verificationUrl,
                    'locale' => $this->locale,
                    'sitename' => iEXContentLanguage('sitename', $this->locale),
                ]
            );
    }

    /**
     * Get the verification URL for the given notifiable.
     *
     * @param  mixed  $notifiable
     */
    protected function verificationUrl(object $notifiable): string
    {
        $frontendUrl = config('app.frontend_url');
        $expires = Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60))->timestamp;

        $params = [
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
            'expires' => $expires,
        ];

        ksort($params);

        $signature = hash_hmac('sha256', http_build_query($params), config('app.key'));

        $params['signature'] = $signature;

        return $frontendUrl . '/confirms/email-verify?' . http_build_query($params);
    }
}
