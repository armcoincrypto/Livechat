<?php

namespace App\Mail\Order;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/***
 * Дополнителнье уведомления для клиента
 *
 * @class OrderChatMail
 */
class OrderChatMail extends Mailable
{
    use Queueable, SerializesModels;

    /***
     * Детали транзакции
     *
     * @return Task
     */
    protected $order;

    /**
     * Дополнительные опции
     *
     * @var array
     */
    protected $options = [];

    /**
     * Create a new message instance.
     *
     * @param  array  $options
     */
    public function __construct(Task $order, $options = [])
    {
        $this->order = $order;
        $this->options = $options;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->subject('Уведомление по заявке №'.iEXSetting('client_id_type_for_order') == 1 ? $this->order->public_id : $this->order->id);

        return $this->markdown('emails.order.order_chat', [
            'subject' => $this->subject,
            'order' => $this->order,
            'options' => $this->options,
        ]);
    }
}
