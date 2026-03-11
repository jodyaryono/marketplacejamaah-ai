<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMessageReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('marketplace-jamaah')];
    }

    public function broadcastAs(): string
    {
        return 'new-message';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'sender_name' => $this->message->sender_name,
            'sender_number' => $this->message->sender_number,
            'message_type' => $this->message->message_type,
            'raw_body' => $this->message->raw_body ? \Illuminate\Support\Str::limit($this->message->raw_body, 100) : null,
            'is_ad' => $this->message->is_ad,
            'created_at' => $this->message->created_at?->toISOString(),
        ];
    }
}
