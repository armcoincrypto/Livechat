<?php

namespace iEXPackages\SmartMailer\Dispatches\Orders;

use App\Models\Task;
use iEXPackages\SmartMailer\SmartMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class OrderPaymentDetailsIssuedMail extends SmartMailable
{
    use Queueable, SerializesModels;

    protected Task $order;

    /**
     * Конструктор письма.
     */
    public function __construct(Task $order)
    {
        parent::__construct($order->task_info->language ?? null);
        $this->order = $order;
    }


    protected function compose(): void
    {
        $this->setSubject(__('smart-mailer::messages.payment_details_subject', ['id' => current_order_id($this->order)]));
        $this->markdown('smart-mailer::orders.order_payment_details_issued', [
            'subject' => $this->subject,
            'order' => $this->order
        ]);
    }
}
