<?php

namespace App\Mail\Admin;

use App\Models\Task;
use App\Models\UserVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;

class AdminNewVerificationAccount extends Mailable
{
    use Queueable, SerializesModels;

    /***
     * Детали
     *
     * @return Task
     */
    protected UserVerification $userVerification;

    /**
     * Create a new message instance.
     */
    public function __construct(UserVerification $userVerification)
    {
        $this->userVerification = $userVerification;
    }

    /**
     * Build the message.
     */
    public function content(): Content
    {
        $this->subject('Верификация личности для '.$this->userVerification->user->name);

        return new Content(
            markdown: 'emails.admin.verification_account_mail',
            with: [
                'item' => $this->userVerification,
                'subject' => $this->subject,
            ],
        );
    }
}
