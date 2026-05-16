<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QueueUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $clinicId,
        public string $action,
        public ?array $entry = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("clinics.{$this->clinicId}.queue");
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'entry' => $this->entry,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
