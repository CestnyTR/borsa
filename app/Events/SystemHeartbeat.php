<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SystemHeartbeat implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(private readonly string $traceId, private readonly string $emittedAt)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.dashboard')];
    }

    public function broadcastAs(): string
    {
        return 'system.heartbeat';
    }

    /**
     * @return array{type: string, version: string, data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function broadcastWith(): array
    {
        return [
            'type' => 'system.heartbeat',
            'version' => '1',
            'data' => [
                'status' => 'ok',
            ],
            'meta' => [
                'emitted_at' => $this->emittedAt,
                'trace_id' => $this->traceId,
            ],
        ];
    }
}
