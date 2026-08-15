<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Client check receipt notification (template: emails.send_client_check).
 */
class SendClientCheckMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        protected Task $order,
        protected array $options = [],
    ) {}

    public function build(): self
    {
        $orderId = iEXSetting('client_id_type_for_order') == 1
            ? $this->order->public_id
            : $this->order->id;

        $subject = (string) ($this->options['subject'] ?? 'Уведомление по заявке №'.$orderId);

        return $this
            ->subject($subject)
            ->markdown('emails.send_client_check', [
                'subject' => $subject,
                'order' => $this->order,
                'options' => $this->options,
            ]);
    }
}
