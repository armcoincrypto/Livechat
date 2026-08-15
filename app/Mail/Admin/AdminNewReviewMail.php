<?php

namespace App\Mail\Admin;

use App\Models\Review;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;

class AdminNewReviewMail extends Mailable
{
    use Queueable, SerializesModels;

    /***
     * Детали
     *
     * @return Task
     */
    protected Review $review;

    /**
     * Create a new message instance.
     */
    public function __construct(Review $review)
    {
        $this->review = $review;
    }

    /**
     * Build the message.
     */
    public function content(): Content
    {
        $this->subject('Новый отзыв к заявке №'.current_order_id($this->review->tasks));

        return new Content(
            markdown: 'emails.admin.new_review_mail',
            with: [
                'item' => $this->review,
                'subject' => $this->subject,
            ],
        );
    }
}
