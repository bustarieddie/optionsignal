<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuoteUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param array<string, mixed> $quotes */
    public function __construct(public array $quotes)
    {
    }

    public function broadcastOn(): Channel
    {
        // Market data is the same for everyone — a public channel.
        return new Channel('quotes');
    }

    public function broadcastAs(): string
    {
        return 'quote.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['quotes' => $this->quotes];
    }
}
