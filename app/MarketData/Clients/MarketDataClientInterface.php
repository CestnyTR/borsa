<?php

namespace App\MarketData\Clients;

use App\MarketData\DTO\QuoteDto;

interface MarketDataClientInterface
{
    /**
     * @return array<int, QuoteDto>
     */
    public function fetchQuotes(): array;

    public function sourceCode(): string;
}

