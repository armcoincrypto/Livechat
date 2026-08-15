<?php

namespace App\Mail\Auth;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;

class LockoutAuthMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Информация о пользователе
     */
    protected User $user;

    /**
     * Дополнительная информация
     */
    protected array $details = [];

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, array $details)
    {
        $this->user = $user;
        $this->details = $details;

        $this->locale = $this->user->language ?? app()->getLocale();
    }

    /**
     * Build the message.
     */
    public function content(): Content
    {
        $subject = __('Обнаружена неудачная попытка входа в ваш аккаунт');

        return new Content(
            markdown: 'emails.auth.lockout',
            with: [
                'subject' => $subject,
                'user' => $this->user,
                'details' => $this->details,
            ]
        );
    }
}
