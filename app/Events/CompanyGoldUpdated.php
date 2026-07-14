<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CompanyGoldUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $meta
     */
    public function __construct(
        private readonly int $companyId,
        private readonly array $items,
        private readonly array $meta
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("company.{$this->companyId}")];
    }

    public function broadcastAs(): string
    {
        return 'company.gold.updated';
    }

    /**
     * @return array{type: string, version: string, data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function broadcastWith(): array
    {
        return [
            'type' => 'company.gold.updated',
            'version' => '1',
            'data' => [
                'company_id' => $this->companyId,
                'items' => $this->items,
            ],
            'meta' => $this->meta,
        ];
    }
}
