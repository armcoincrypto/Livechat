<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class MessagePosted implements ShouldBroadcast
{
    use SerializesModels;

    protected $message;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($message)
    {
        $this->message = $message;
    }

    public function broadcastWith()
    {
        // This must always be an array. Since it will be parsed with json_encode()
        return [
            'message' => $this->message,
        ];
    }

    public function broadcastAs()
    {
        return 'server.created';
    }

    public function broadcastOn()
    {
        return new Channel('messages');
    }
}
