<?php

namespace App\Mail\Order;

use App\Models\HistoryRecalculation;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderRecountMail extends Mailable
{
    use Queueable, SerializesModels;

    protected Task $order;
    protected ?HistoryRecalculation $historyRecalculation;

    /**
     * Создание экземпляра письма.
     */
    public function __construct(Task $order)
    {
        $this->order = $order;

        $this->historyRecalculation = HistoryRecalculation::where('id_task', $order->id)
            ->latest('id')
            ->first();

        $this->locale($order->task_info->language ?? app()->getLocale());
    }

    /**
     * Формирование понятной темы письма.
     */
    protected function getSubject(): string
    {
        $orderId = iEXSetting('client_id_type_for_order') == 1 ? $this->order->public_id : $this->order->id;

        return sprintf('%s – %s',
            iEXContentLanguage('sitename', $this->locale),
            __('Заявка № :id пересчитана', ['id' => $orderId])
        );
    }

    /**
     * Сборка и отправка письма.
     */
    public function build(): static
    {
        $subject = $this->getSubject();

        return $this->subject($subject)
            ->markdown('emails.order.order_recount', [
                'subject' => $subject,
                'order' => $this->order,
                'recount' => $this->historyRecalculation,
            ]);
    }
}
