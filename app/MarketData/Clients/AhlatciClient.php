<?php

namespace App\MarketData\Clients;

use App\MarketData\DTO\QuoteDto;
use App\MarketData\Support\NumberParser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class AhlatciClient implements MarketDataClientInterface
{
    private const URL = 'https://www.ahlatcidoviz.com.tr/kr.aspx';

    public function sourceCode(): string
    {
        return 'AHLATCI';
    }

    public function fetchQuotes(): array
    {
        $this->rateLimit();

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: ' . config('app.scraper_user_agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'),
                    'Accept: text/html,application/xhtml+xml',
                ],
                'timeout' => 15,
            ],
        ]);

        $html = @file_get_contents(self::URL, false, $context);

        if ($html === false) {
            throw new RuntimeException('Ahlatcı Döviz web sitesine erişilemedi');
        }

        $fetchedAt = CarbonImmutable::now();
        $quotes = [];

        try {
            [$xauBuy, $xauSell, $xauCells] = $this->parseSymbolRow($html, 'XAU');
            $quotes[] = new QuoteDto(
                sourceCode: $this->sourceCode(),
                instrument: 'HAS',
                bid: $xauBuy,
                ask: $xauSell,
                currency: 'TRY',
                fetchedAt: $fetchedAt,
                rawPayload: [
                    'SMB' => 'XAU',
                    'cells' => $xauCells,
                ]
            );
        } catch (\Throwable $e) {
            $quotes[] = new QuoteDto(
                sourceCode: $this->sourceCode(),
                instrument: 'HAS',
                bid: null,
                ask: null,
                currency: 'TRY',
                fetchedAt: $fetchedAt,
                rawPayload: [
                    'SMB' => 'XAU',
                    'error' => $e->getMessage(),
                ]
            );
        }

        try {
            [$xauUsdBuy, $xauUsdSell, $xauUsdCells] = $this->parseSymbolRow($html, 'XAUUSD');
            $quotes[] = new QuoteDto(
                sourceCode: $this->sourceCode(),
                instrument: 'ONS',
                bid: $xauUsdBuy,
                ask: $xauUsdSell,
                currency: 'USD',
                fetchedAt: $fetchedAt,
                rawPayload: [
                    'SMB' => 'XAUUSD',
                    'cells' => $xauUsdCells,
                ]
            );
        } catch (\Throwable $e) {
            $quotes[] = new QuoteDto(
                sourceCode: $this->sourceCode(),
                instrument: 'ONS',
                bid: null,
                ask: null,
                currency: 'USD',
                fetchedAt: $fetchedAt,
                rawPayload: [
                    'SMB' => 'XAUUSD',
                    'error' => $e->getMessage(),
                ]
            );
        }

        return $quotes;
    }

    /**
     * @return array{0: float|null, 1: float, 2: array<int, string>}
     */
    private function parseSymbolRow(string $html, string $symbol): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);

        $upper = strtoupper($symbol);
        $cells = $xpath->query("//th[contains(translate(normalize-space(text()), 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'), '{$upper}')]");

        if (!$cells || $cells->length === 0) {
            throw new RuntimeException("{$symbol} verisi bulunamadı");
        }

        foreach ($cells as $th) {
            $thText = strtoupper(trim((string) $th->textContent));
            if ($thText !== $upper) {
                continue;
            }
            $row = $th->parentNode;
            if (!$row) {
                continue;
            }
            $tds = $xpath->query('.//td', $row);
            if (!$tds || $tds->length < 2) {
                continue;
            }

            $cellTexts = [];
            foreach ($tds as $td) {
                $cellTexts[] = trim((string) $td->textContent);
            }

            $buy = NumberParser::parseLocaleNumber($cellTexts[0] ?? null);
            $sell = NumberParser::parseLocaleNumber($cellTexts[1] ?? null);

            if ($sell === null) {
                continue;
            }

            return [$buy, $sell, $cellTexts];
        }

        throw new RuntimeException("{$symbol} satırı parse edilemedi");
    }

    private function rateLimit(): void
    {
        $ok = Cache::add("ratelimit:source:{$this->sourceCode()}", 1, 1);
        if (!$ok) {
            throw new RuntimeException('Rate limited');
        }
    }
}
