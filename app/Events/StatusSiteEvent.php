<?php
declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use iEXPackages\WorkStatus\Services\WorkStatusService;

class StatusSiteEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Текст письма
     */
    protected string $message;

    /**
     * Тип письма
     */
    protected int $type;

    /**
     * Create a new event instance.
     */
    public function __construct(string $message, int $type)
    {
        $this->message = $message;
        $this->type = $type;
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'type' => $this->type,
            'status' => app(WorkStatusService::class)->isOffline(),
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel|array
    {
        return new Channel('status.site');
    }

    public function broadcastAs(): string
    {
        return 'status.site.cast';
    }
}
