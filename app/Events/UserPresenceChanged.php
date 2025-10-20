<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserPresenceChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var int */
    public $user_id;

    /** @var string */
    public $status;

    /**
     * Create a new event instance.
     */
    public function __construct(int $user_id, string $status)
    {
        $this->user_id = $user_id;
        $this->status = $status;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        // Public channel for presence updates across the app
        return new Channel('presence');
    }

    /**
     * Customize the event name on the client.
     */
    public function broadcastAs(): string
    {
        return 'UserPresenceChanged';
    }

    /**
     * Additional data to broadcast with the event.
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user_id,
            'status' => $this->status,
        ];
    }
}
