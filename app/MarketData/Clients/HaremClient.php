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
            'User-Agent' => config('app.scraper_user_agent', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'),
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
        $onsRow = $this->extractOnsRow($data);

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
        $row = $this->extractRow(
            data: $data,
            normalizedCandidates: ['HASALTIN', 'HAS_ALTIN', 'HASAL', 'HAS'],
            fieldCandidates: ['HASALTIN', 'HAS'],
            nameContainsAll: ['HAS', 'ALTIN'],
        );

        if ($row !== null) {
            return $row;
        }

        throw new RuntimeException('HAS row missing (keys: ' . implode(',', array_slice(array_keys($data), 0, 50)) . ')');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function extractOnsRow(array $data): array
    {
        $row = $this->extractRow(
            data: $data,
            normalizedCandidates: ['ONS', 'XAUUSD'],
            fieldCandidates: ['ONS', 'XAUUSD', 'XAU/USD'],
            nameContainsAll: ['ONS'],
        );

        if ($row !== null) {
            return $row;
        }

        throw new RuntimeException('ONS row missing (keys: ' . implode(',', array_slice(array_keys($data), 0, 50)) . ')');
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $normalizedCandidates
     * @param array<int, string> $fieldCandidates
     * @param array<int, string> $nameContainsAll
     * @param int $depth
     * @return array<string, mixed>|null
     */
    private function extractRow(
        array $data,
        array $normalizedCandidates,
        array $fieldCandidates,
        array $nameContainsAll,
        int $depth = 0
    ): ?array {
        $normalizedCandidates = array_map('strtoupper', $normalizedCandidates);
        $fieldCandidates = array_map('strtoupper', $fieldCandidates);
        $nameContainsAll = array_map('strtoupper', $nameContainsAll);

        foreach ($data as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            $normalizedKey = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $key) ?? '');
            if ($normalizedKey !== '' && in_array($normalizedKey, $normalizedCandidates, true)) {
                return $value;
            }

            $symbol = strtoupper((string) ($value['kod'] ?? $value['code'] ?? $value['symbol'] ?? $value['parite'] ?? ''));
            if ($symbol !== '' && in_array($symbol, $fieldCandidates, true)) {
                return $value;
            }

            $name = strtoupper((string) ($value['adi'] ?? $value['name'] ?? $value['baslik'] ?? $value['title'] ?? ''));
            if ($name !== '' && $this->containsAll($name, $nameContainsAll)) {
                return $value;
            }

            if ($depth < 2) {
                $nested = $this->extractRow($value, $normalizedCandidates, $fieldCandidates, $nameContainsAll, $depth + 1);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $needles
     */
    private function containsAll(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle === '') {
                continue;
            }
            if (!str_contains($haystack, $needle)) {
                return false;
            }
        }
        return true;
    }

    private function rateLimit(): void
    {
        $ok = Cache::add("ratelimit:source:{$this->sourceCode()}", 1, 1);
        if (!$ok) {
            throw new RuntimeException('Rate limited');
        }
    }
}
