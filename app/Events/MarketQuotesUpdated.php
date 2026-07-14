<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MarketQuotesUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    /**
     * @param array<int, array<string, mixed>> $quotes
     * @param array<string, mixed> $meta
     * @param array<int, string> $channelNames
     */
    public function __construct(
        private readonly array $quotes,
        private readonly array $meta,
        private readonly array $channelNames
    ) {
    }

    public function broadcastOn(): array
    {
        return array_map(fn (string $name) => new PrivateChannel($name), $this->channelNames);
    }

    public function broadcastAs(): string
    {
        return 'market.quotes.updated';
    }

    /**
     * @return array{type: string, version: string, data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function broadcastWith(): array
    {
        return [
            'type' => 'market.quotes.updated',
            'version' => '1',
            'data' => [
                'quotes' => $this->quotes,
            ],
            'meta' => $this->meta,
        ];
    }
}
