<?php

declare(strict_types=1);

namespace App\Notifications;

use iEXPackages\AuthAudit\Models\AuthEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class NewDeviceNotification extends Notification
{
    use Queueable;

    /**
     * Контекст входа (новая система аудита).
     */
    private AuthEvent $authenticationLog;

    /**
     * Create a new notification instance.
     */
    public function __construct(AuthEvent $authenticationLog, ?string $locale = null)
    {
        $this->authenticationLog = $authenticationLog;

        // Язык письма: приоритет у явного аргумента, затем у языка пользователя, иначе текущая локаль приложения.
        $userLocale = $authenticationLog->user?->language;

        $this->locale($locale ?? $userLocale ?? app()->getLocale());
    }

    /**
     * Каналы доставки уведомления.
     */
    public function via(object $notifiable): array
    {
        return $notifiable->notifyAuthenticationLogVia();
    }

    /**
     * Mail уведомление.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = __('Вход с нового устройства в ваш аккаунт');

        $ctx = $this->extractContext();

        return (new MailMessage)
            ->subject($subject)
            ->markdown('emails.auth.new_device', [
                'sitename' => iEXContentLanguage('sitename', $this->locale),
                'subject' => $subject,
                'account' => $notifiable,

                // Шаблон может использовать log (оставляем ключ)
                'log' => $this->authenticationLog,

                // Старые ключи для совместимости шаблона
                'time' => $ctx['time'],
                'ipAddress' => $ctx['ip'],
                'browser' => $ctx['browser'],

                // Доп. данные (если захочешь вывести в шаблоне)
                'geoLabel' => $ctx['geo_label'],
                'device' => $ctx['device'],
                'os' => $ctx['os'],
            ]);
    }

    /**
     * Извлечение данных из AuthEvent.
     *
     * @return array{time:mixed,ip:string,browser:string,geo_label:?string,device:?string,os:?string}
     */
    private function extractContext(): array
    {
        $meta = is_array($this->authenticationLog->meta) ? $this->authenticationLog->meta : [];

        $geoLabel = null;
        if (is_string($meta['geo_label'] ?? null)) {
            $geoLabel = $meta['geo_label'];
        } elseif (is_array($meta['geo'] ?? null) && is_string(($meta['geo']['geo_label'] ?? null))) {
            $geoLabel = $meta['geo']['geo_label'];
        }

        return [
            'time' => $this->authenticationLog->created_at,
            'ip' => (string) ($this->authenticationLog->ip ?? ''),
            // В твоём шаблоне ключ 'browser' фактически использовался как user_agent
            'browser' => (string) ($this->authenticationLog->user_agent ?? ''),
            'geo_label' => $geoLabel,
            'device' => $this->authenticationLog->device,
            'os' => $this->authenticationLog->os,
        ];
    }
}
