<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StorePricesUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    /**
     * @param array<int, array<string, mixed>> $prices
     * @param array<string, mixed> $meta
     */
    public function __construct(
        private readonly int $storeId,
        private readonly array $prices,
        private readonly array $meta
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("store.{$this->storeId}")];
    }

    public function broadcastAs(): string
    {
        return 'store.prices.updated';
    }

    /**
     * @return array{type: string, version: string, data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function broadcastWith(): array
    {
        return [
            'type' => 'store.prices.updated',
            'version' => '1',
            'data' => [
                'store_id' => $this->storeId,
                'prices' => $this->prices,
            ],
            'meta' => $this->meta,
        ];
    }
}
