<?php

namespace App\MarketData\Clients;

use InvalidArgumentException;

class MarketDataClientFactory
{
    public function make(string $sourceCode): MarketDataClientInterface
    {
        return match (strtoupper($sourceCode)) {
            'HAREM' => new HaremClient(),
            'AHLATCI' => new AhlatciClient(),
            'NADIR' => new NadirClient(),
            default => throw new InvalidArgumentException("Unsupported source: {$sourceCode}"),
        };
    }
}

