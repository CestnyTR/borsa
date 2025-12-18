<?php

namespace App\MarketData\Clients;

use App\MarketData\DTO\QuoteDto;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AhlatciClient implements MarketDataClientInterface
{
    private const API_URL = 'https://www.ahlatcidoviz.com.tr/static/currencies.json';

    public function sourceCode(): string
    {
        return 'AHLATCI';
    }

    public function fetchQuotes(): array
    {
        $this->rateLimit();

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'User-Agent' => config('app.scraper_user_agent', 'Mozilla/5.0'),
        ])
            ->retry(3, 350)
            ->timeout(15)
            ->get(self::API_URL);

        $response->throw();

        $payload = $response->json();
        if (!is_array($payload)) {
            throw new RuntimeException('Invalid JSON payload');
        }

        $xau = $this->findRow($payload, 'XAU');
        $xauusd = $this->findRow($payload, 'XAUUSD');

        $fetchedAt = CarbonImmutable::now();

        return [
            new QuoteDto(
                sourceCode: $this->sourceCode(),
                instrument: 'HAS',
                bid: $this->parseNumeric($this->firstValue($xau, ['Al', 'Alis', 'Alış', 'alis', 'buy', 'Buying'])),
                ask: $this->parseNumeric($this->firstValue($xau, ['St', 'Satis', 'Satış', 'satis', 'sell', 'Selling'])),
                currency: 'TRY',
                fetchedAt: $fetchedAt,
                rawPayload: $xau
            ),
            new QuoteDto(
                sourceCode: $this->sourceCode(),
                instrument: 'ONS',
                bid: $this->parseNumeric($this->firstValue($xauusd, ['Al', 'Alis', 'Alış', 'alis', 'buy', 'Buying'])),
                ask: $this->parseNumeric($this->firstValue($xauusd, ['St', 'Satis', 'Satış', 'satis', 'sell', 'Selling'])),
                currency: 'USD',
                fetchedAt: $fetchedAt,
                rawPayload: $xauusd
            ),
        ];
    }

    /**
     * @param array<int, mixed> $payload
     * @return array<string, mixed>
     */
    private function findRow(array $payload, string $smb): array
    {
        $needle = strtoupper($smb);
        foreach ($payload as $item) {
            if (!is_array($item)) {
                continue;
            }
            $value = strtoupper((string) ($item['SMB'] ?? ''));
            if ($value === $needle || ($value !== '' && str_starts_with($value, $needle))) {
                return $item;
            }
        }

        throw new RuntimeException("Row not found: {$smb}");
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $keys
     */
    private function firstValue(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }
        return null;
    }

    private function parseNumeric(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
            return (float) $value;
        }

        return null;
    }

    private function rateLimit(): void
    {
        $ok = Cache::add("ratelimit:source:{$this->sourceCode()}", 1, 1);
        if (!$ok) {
            throw new RuntimeException('Rate limited');
        }
    }
}
