<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GoldPricesUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    /**
     * @param array<int, int> $sourceIds
     */
    public function __construct(
        private readonly int $assetId,
        private readonly float $sellPrice,
        private readonly ?float $buyPrice,
        private readonly string $validFrom,
        private readonly array $sourceIds,
        private readonly string $traceId,
        private readonly string $lastUpdateAt
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.dashboard')];
    }

    public function broadcastAs(): string
    {
        return 'gold.prices.updated';
    }

    /**
     * @return array{type: string, version: string, data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function broadcastWith(): array
    {
        return [
            'type' => 'gold.prices.updated',
            'version' => '1',
            'data' => [
                'asset_id' => $this->assetId,
                'sell_price' => $this->sellPrice,
                'buy_price' => $this->buyPrice,
                'valid_from' => $this->validFrom,
            ],
            'meta' => [
                'emitted_at' => now()->toIso8601String(),
                'source_ids' => $this->sourceIds,
                'trace_id' => $this->traceId,
                'last_update_at' => $this->lastUpdateAt,
            ],
        ];
    }
}
