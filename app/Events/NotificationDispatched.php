<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class NotificationDispatched implements ShouldBroadcast
{
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Notification $notification)
    {
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('users.'.$this->notification->user_id.'.notifications');
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'event_type' => $this->notification->event_type,
            'title' => $this->notification->title,
            'body' => $this->notification->body,
            'entity_type' => $this->notification->entity_type,
            'entity_id' => $this->notification->entity_id,
            'meta' => $this->notification->meta,
            'created_at' => $this->notification->created_at?->toIso8601String(),
        ];
    }
}
