<?php

namespace iEXPackages\SmartMailer\Dispatches\Orders;

use App\Models\Task;
use iEXPackages\SmartMailer\SmartMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class OrderRejectedMail extends SmartMailable
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
        $this->setSubject(__('smart-mailer::messages.order_rejected_subject', ['id' => current_order_id($this->order)]))
            ->markdown('smart-mailer::orders.order_reject', [
                'subject' => $this->subject,
                'order' => $this->order
            ]);
    }
}
