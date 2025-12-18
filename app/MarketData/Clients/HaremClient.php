<?php

namespace App\MarketData\Clients;

use App\MarketData\DTO\QuoteDto;
use App\MarketData\Support\NumberParser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HaremClient implements MarketDataClientInterface
{
    private const API_URL = 'https://canlipiyasalar.haremaltin.com/tmp/altin.json';

    public function sourceCode(): string
    {
        return 'HAREM';
    }

    public function fetchQuotes(): array
    {
        $this->rateLimit();

        $response = Http::withHeaders([
            'Accept' => 'application/json, text/plain, */*',
            'Referer' => 'https://canlipiyasalar.haremaltin.com/',
            'User-Agent' => config('app.scraper_user_agent', 'Mozilla/5.0'),
        ])
            ->retry(3, 350)
            ->timeout(15)
            ->get(self::API_URL, ['dil_kodu' => 'tr']);

        $response->throw();

        $payload = $response->json();
        if (!is_array($payload)) {
            throw new RuntimeException('Invalid JSON payload');
        }

        $data = $payload['data'] ?? null;
        if (!is_array($data)) {
            throw new RuntimeException('Invalid payload: data missing');
        }

        $fetchedAt = CarbonImmutable::now();

        $hasRow = $this->extractHasRow($data);
        $onsRow = $data['ONS'] ?? null;
        if (!is_array($onsRow)) {
            throw new RuntimeException('ONS row missing');
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

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function extractHasRow(array $data): array
    {
        $candidateKeys = ['HASALTIN', 'HAS_ALTIN', 'HASAL', 'HAS'];
        foreach ($candidateKeys as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }

        foreach ($data as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            $normalizedKey = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $key) ?? '');
            if ($normalizedKey === 'HASALTIN') {
                return $value;
            }
        }

        throw new RuntimeException('HAS row missing');
    }

    private function rateLimit(): void
    {
        $ok = Cache::add("ratelimit:source:{$this->sourceCode()}", 1, 1);
        if (!$ok) {
            throw new RuntimeException('Rate limited');
        }
    }
}
