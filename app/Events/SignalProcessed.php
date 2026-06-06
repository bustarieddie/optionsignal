<?php

namespace App\Events;

use App\Models\Signal;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SignalProcessed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Signal $signal)
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.' . $this->signal->user_id);
    }

    public function broadcastAs(): string
    {
        return 'signal.new';
    }

    /**
     * Secret-free projection for the dashboard.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->signal->id,
            'ticker' => $this->signal->ticker,
            'timeframe' => $this->signal->timeframe,
            'signal_type' => $this->signal->signal_type,
            'rs_status' => $this->signal->rs_status,
            'grade' => $this->signal->grade,
            'total_score' => $this->signal->total_score,
            'price' => $this->signal->price,
            'color' => $this->signal->colorCode(),
            'strategy' => $this->signal->strategy?->name,
            'occurred_at' => optional($this->signal->occurred_at)->format('M j, Y H:i:s'),
            'url' => route('signals.show', $this->signal->id),
        ];
    }
}
