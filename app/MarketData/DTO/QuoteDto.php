<?php

namespace App\MarketData\DTO;

use Carbon\CarbonInterface;

class QuoteDto
{
    /**
     * @param array<string, mixed>|null $rawPayload
     */
    public function __construct(
        public readonly string $sourceCode,
        public readonly string $instrument,
        public readonly ?float $bid,
        public readonly ?float $ask,
        public readonly string $currency,
        public readonly CarbonInterface $fetchedAt,
        public readonly ?array $rawPayload = null,
    ) {
    }

    /**
     * @return array{source: string, instrument: string, bid: float|null, ask: float|null, currency: string, fetched_at: string, raw_payload: array<string, mixed>|null}
     */
    public function toArray(): array
    {
        return [
            'source' => $this->sourceCode,
            'instrument' => $this->instrument,
            'bid' => $this->bid,
            'ask' => $this->ask,
            'currency' => $this->currency,
            'fetched_at' => $this->fetchedAt->toIso8601String(),
            'raw_payload' => $this->rawPayload,
        ];
    }
}

