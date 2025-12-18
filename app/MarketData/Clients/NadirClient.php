<?php

namespace App\MarketData\Clients;

use App\MarketData\DTO\QuoteDto;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class NadirClient implements MarketDataClientInterface
{
    private const API_URL = 'https://www.nadirdoviz.com/api/KurOku';
    private const API_KEY = '1397x2684y3971z6842';
    private const API_GROUP_ID = 1;

    public function sourceCode(): string
    {
        return 'NADIR';
    }

    public function fetchQuotes(): array
    {
        $this->rateLimit();

        $payload = [
            'grupId' => self::API_GROUP_ID,
            'kayitSayisi' => null,
            'grupListYukle' => false,
            'ilkGrupKurlariGetir' => false,
            'anahtar' => self::API_KEY,
            'bolgeMarjListesiniBellegeYukle' => false,
            'sessionId' => '',
        ];

        $response = Http::withHeaders([
            'Accept' => 'application/json, text/plain, */*',
            'Origin' => 'https://www.nadirdoviz.com',
            'Referer' => 'https://www.nadirdoviz.com/fiyat-ekrani',
            'User-Agent' => config('app.scraper_user_agent', 'Mozilla/5.0'),
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->retry(3, 350)
            ->timeout(15)
            ->post(self::API_URL, $payload);

        $response->throw();

        $body = $response->json();
        if (!is_array($body)) {
            throw new RuntimeException('Invalid JSON payload');
        }

        $list = $body['list'] ?? null;
        if (!is_array($list) || $list === []) {
            throw new RuntimeException('Invalid payload: list missing');
        }

        $hasRow = $this->findRow($list, targetParites: ['AU/TL', 'ALTIN/TL'], targetBasliks: ['ALTIN/TL', 'ALTIN TL', 'ALTIN / TL']);
        $onsRow = $this->findRow($list, targetParites: ['XAU/USD'], targetBasliks: ['ALTIN/ONS', 'ALTIN ONS', 'ALTIN / ONS']);

        $fetchedAt = CarbonImmutable::now();

        return [
            new QuoteDto(
                sourceCode: $this->sourceCode(),
                instrument: 'HAS',
                bid: $this->parseNumeric($hasRow['alis'] ?? null),
                ask: $this->parseNumeric($hasRow['satis'] ?? null),
                currency: 'TRY',
                fetchedAt: $fetchedAt,
                rawPayload: $hasRow
            ),
            new QuoteDto(
                sourceCode: $this->sourceCode(),
                instrument: 'ONS',
                bid: $this->parseNumeric($onsRow['alis'] ?? null),
                ask: $this->parseNumeric($onsRow['satis'] ?? null),
                currency: 'USD',
                fetchedAt: $fetchedAt,
                rawPayload: $onsRow
            ),
        ];
    }

    /**
     * @param array<int, mixed> $list
     * @param array<int, string> $targetParites
     * @param array<int, string> $targetBasliks
     * @return array<string, mixed>
     */
    private function findRow(array $list, array $targetParites, array $targetBasliks): array
    {
        $targetParites = array_map('strtoupper', $targetParites);
        $targetBasliks = array_map('strtoupper', $targetBasliks);

        foreach ($list as $row) {
            if (!is_array($row)) {
                continue;
            }

            $parite = strtoupper((string) ($row['parite'] ?? ''));
            $baslik = Str::upper(Str::of((string) ($row['baslik'] ?? ''))
                ->replace('İ', 'I')
                ->replace('ı', 'I')
                ->value());

            if (in_array($parite, $targetParites, true) || in_array($baslik, $targetBasliks, true)) {
                return $row;
            }
        }

        throw new RuntimeException('Target row not found');
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
