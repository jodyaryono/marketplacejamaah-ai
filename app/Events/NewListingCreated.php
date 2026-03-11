<?php

namespace App\Events;

use App\Models\Listing;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewListingCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Listing $listing
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('marketplace-jamaah')];
    }

    public function broadcastAs(): string
    {
        return 'new-listing';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->listing->id,
            'title' => $this->listing->title,
            'price_label' => $this->listing->price_formatted,
            'category' => $this->listing->category?->name,
            'contact_name' => $this->listing->contact_name,
            'status' => $this->listing->status,
            'created_at' => $this->listing->created_at?->toISOString(),
        ];
    }
}
