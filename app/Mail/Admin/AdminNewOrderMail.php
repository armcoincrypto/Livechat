<?php

namespace App\Mail\Admin;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;

class AdminNewOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    /***
     * Детали заявки
     *
     * @return Task
     */
    protected Task $order;

    /**
     * Create a new message instance.
     */
    public function __construct(Task $order)
    {
        $this->order = $order;
    }

    /**
     * Build the message.
     */
    public function content(): Content
    {
        $this->subject('Новая заявка №'.current_order_id($this->order));

        return new Content(
            markdown: 'emails.admin.new_order_mail',
            with: [
                'order' => $this->order,
                'subject' => $this->subject,
            ]
        );
    }
}
