<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderChatMessagesEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $public_id;

    public int $user_id;

    public array $message;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(int $public_id, int $user_id, array $message)
    {
        $this->public_id = $public_id;
        $this->user_id = $user_id;
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel|PrivateChannel|array
    {
        return new PrivateChannel('order.chat.messages.'.$this->public_id);
    }

    public function broadcastAs(): string
    {
        return 'order.chat.messages.as';
    }
}
