<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * First-time account setup after order auto-registration.
 * Reuses the Laravel password-reset token/URL contract (reset-credentials).
 *
 * Prefer embedding the set-password CTA in OrderCreatedMail (single email).
 * This notification remains for manual / legacy dispatch only.
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
        return rtrim((string) config('app.frontend_url'), '/')
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
            ->subject(__('smart-mailer::messages.account_section_title'))
            ->greeting(__('smart-mailer::messages.greeting'))
            ->line(__('smart-mailer::messages.account_section_intro'))
            ->line(__('smart-mailer::messages.account_section_set_password_hint'))
            ->action(__('smart-mailer::messages.set_password'), $url)
            ->line(__('smart-mailer::messages.account_section_continue_without'))
            ->line(__('smart-mailer::messages.account_section_security'))
            ->line(__('This link is available for :count minutes.', ['count' => $expireMinutes]));
    }
}
