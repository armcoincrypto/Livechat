<?php

namespace iEXPackages\SmartMailer\Dispatches\Orders;

use App\Models\Task;
use iEXPackages\SmartMailer\SmartMailable;
use iEXPackages\Transaction\Facades\TransactionFacade;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class OrderCompletedMail extends SmartMailable
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
        $transaction = TransactionFacade::init($this->order);
        $item = $transaction->getTransaction();

        $this->setSubject(__('smart-mailer::messages.order_completed_subject', ['id' => current_order_id($this->order)]));
        $this->markdown('smart-mailer::orders.order_completed', [
            'subject' => $this->subject,
            'order' => $item,
            'tasks_fields'         => $transaction->getTasksFields(),
        ]);
    }
}
