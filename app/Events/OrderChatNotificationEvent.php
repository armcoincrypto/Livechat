<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JetBrains\PhpStorm\ArrayShape;

class OrderChatNotificationEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Детали уведомлений
     */
    protected array $item;

    /**
     * Create a new event instance.
     */
    public function __construct(array $array)
    {
        $this->item = $array;
    }

    #[ArrayShape(['message' => 'mixed', 'order_id' => 'integer'])]
    public function broadcastWith(): array
    {
        return [
            'message' => $this->item['message'],
            'order_id' => $this->item['order_id'],
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel|PrivateChannel|array
    {
        return new PrivateChannel('order.chat.notification.'.$this->item['user_id']);
    }

    public function broadcastAs(): string
    {
        return 'order.chat.notification.as';
    }
}
