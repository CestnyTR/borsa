<?php

namespace App\MarketData\Clients;

use App\MarketData\DTO\QuoteDto;
use App\MarketData\Support\HaremSocketReader;
use App\MarketData\Support\NumberParser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Reads HAS (gram gold, TRY) and ONS (troy ounce, USD) from HAREM's Socket.IO
 * feed. See {@see HaremSocketReader} for why the old HTTP endpoint is gone.
 */
class HaremClient implements MarketDataClientInterface
{
    public function __construct(
        private readonly HaremSocketReader $reader = new HaremSocketReader(),
    ) {
    }

    public function sourceCode(): string
    {
        return 'HAREM';
    }

    public function fetchQuotes(): array
    {
        $this->rateLimit();

        $data = $this->reader->fetchSnapshot();

        $fetchedAt = CarbonImmutable::now();

        // Gram gold ("HAS Altın") is published under the ALTIN key in TRY;
        // the troy ounce under ONS in USD.
        $hasRow = HaremSocketReader::extractRow($data, ['ALTIN', 'HASALTIN', 'HAS_ALTIN', 'HAS'], ['ALTIN', 'HASALTIN', 'HAS']);
        $onsRow = HaremSocketReader::extractRow($data, ['ONS', 'XAUUSD'], ['ONS', 'XAUUSD', 'XAU/USD']);

        if ($hasRow === null) {
            throw new RuntimeException('HAS row missing (keys: ' . implode(',', array_slice(array_keys($data), 0, 50)) . ')');
        }
        if ($onsRow === null) {
            throw new RuntimeException('ONS row missing (keys: ' . implode(',', array_slice(array_keys($data), 0, 50)) . ')');
        }

        return [
            new QuoteDto(
                sourceCode: $this->sourceCode(),
                instrument: 'HAS',
                bid: NumberParser::parseLocaleNumber($hasRow['alis'] ?? null),
                ask: NumberParser::parseLocaleNumber($hasRow['satis'] ?? null),
                currency: 'TRY',
                fetchedAt: $fetchedAt,
                rawPayload: $hasRow
            ),
            new QuoteDto(
                sourceCode: $this->sourceCode(),
                instrument: 'ONS',
                bid: NumberParser::parseLocaleNumber($onsRow['alis'] ?? null),
                ask: NumberParser::parseLocaleNumber($onsRow['satis'] ?? null),
                currency: 'USD',
                fetchedAt: $fetchedAt,
                rawPayload: $onsRow
            ),
        ];
    }

    private function rateLimit(): void
    {
        $ok = Cache::add("ratelimit:source:{$this->sourceCode()}", 1, 1);
        if (!$ok) {
            throw new RuntimeException('Rate limited');
        }
    }
}
