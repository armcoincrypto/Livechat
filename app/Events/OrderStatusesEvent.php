<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusesEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Детали заявки
     */
    protected Task $order;

    /**
     * Create a new event instance.
     */
    public function __construct(Task $order)
    {
        $this->order = $order;
    }

    public function broadcastWith(): array
    {
        $client_order_id = current_order_id($this->order);

        $options = [];
        $attributes = [];
        if ($this->order->status == 3) {
            $options = [
                'message' => sprintf('Заявка №%s принят на обработку', $client_order_id),
                'className' => 'handler-notification-bg',
                'type' => 'wait',
            ];
        } elseif ($this->order->status == 4) {

            $options = [
                'order_id' => iEXSetting('client_id_type_for_order') == 1 ? $this->order->public_id : $this->order->id,
                'message' => sprintf('Заявка №%s успешно выполнено', $client_order_id),
                'className' => 'successful-notification-bg',
                'type' => 'success',
                'is_modal' => 0,
                'is_snackbar' => (int) iEXSetting('iex_snackbar_success_order'),
            ];

        } elseif ($this->order->status == 5) {
            $options = [
                'message' => sprintf('Заявка №%s удалена', $client_order_id),
                'className' => 'failed-notification-bg',
                'type' => 'failed',
            ];
        } elseif ($this->order->status == 8) {
            $options = [
                'message' => sprintf('Заявка №%s заморожено', $client_order_id),
                'className' => 'defer-notification-bg',
                'type' => 'defer',
            ];
        }

        return $options;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('order.user.'.$this->order->id_user);
    }

    public function broadcastAs()
    {
        return 'order.statusses';
    }
}
