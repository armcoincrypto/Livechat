<?php

namespace iEXPackages\SmartMailer\Dispatches\Orders;

use App\Models\Task;
use iEXPackages\SmartMailer\SmartMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class OrderShotBlackListMail extends SmartMailable
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
        $this->setSubject(__('К сожалению, ваша заявка черный список'))
            ->markdown('smart-mailer::orders.order_shot_black_list', [
                'subject' => $this->subject,
                'order' => $this->order
            ]);
    }
}
