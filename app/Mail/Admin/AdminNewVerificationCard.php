<?php

namespace App\Mail\Admin;

use App\Models\Task;
use App\Models\VerificationCard;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;

class AdminNewVerificationCard extends Mailable
{
    use Queueable, SerializesModels;

    /***
     * Детали
     *
     * @return Task
     */
    protected VerificationCard $verificationCard;

    /**
     * Create a new message instance.
     */
    public function __construct(VerificationCard $verificationCard)
    {
        $this->verificationCard = $verificationCard;
    }

    /**
     * Build the message.
     */
    public function content(): Content
    {
        $order = Task::find($this->verificationCard->id_order);
        if (isset($order)) {
            $this->subject('Верификация счета по заявке №'.current_order_id($order));
        } else {
            $this->subject('Верификация счета');
        }

        return new Content(
            markdown: 'emails.admin.verification_card_mail',
            with: [
                'item' => $this->verificationCard,
                'subject' => $this->subject,
                'order' => $order,
            ],
        );
    }
}
