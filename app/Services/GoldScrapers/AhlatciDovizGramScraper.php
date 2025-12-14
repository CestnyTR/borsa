<?php

namespace App\Services\GoldScrapers;

use Exception;
use Illuminate\Support\Facades\Log;

class AhlatciDovizGramScraper implements GoldScraperInterface
{
    private const URL = 'https://www.ahlatcidoviz.com.tr/kr.aspx';

    public function getSourceCode(): string
    {
        return 'AHLATCI_DOVIZ';
    }

    public function fetchPrice(): array
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        'Accept: text/html,application/xhtml+xml',
                    ],
                    'timeout' => 15,
                ],
            ]);

            $html = @file_get_contents(self::URL, false, $context);

            if ($html === false) {
                throw new Exception('Ahlatcı Döviz web sitesine erişilemedi');
            }

            Log::info('AhlatciDovizGramScraper: HTML fetched', [
                'size' => strlen($html),
            ]);

            return $this->parseHtml($html);
        } catch (Exception $e) {
            Log::error('AhlatciDovizGramScraper error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function parseHtml(string $html): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);

        // XAU satırı: <tr><th>XAU</th><td>Alış</td><td>Satış</td></tr>
        $xauCells = $xpath->query("//th[contains(translate(normalize-space(text()), 'xau', 'XAU'), 'XAU')]");

        if ($xauCells->length === 0) {
            throw new Exception('XAU verisi bulunamadı');
        }

        $buyPrice = null;
        $sellPrice = null;

        foreach ($xauCells as $xauCell) {
            $row = $xauCell->parentNode;
            $cells = $xpath->query('.//td', $row);

            if ($cells->length < 2) {
                continue;
            }

            $cellTexts = [];
            foreach ($cells as $cell) {
                $cellTexts[] = trim($cell->textContent);
            }

            Log::info('AhlatciDovizGramScraper: XAU row found', [
                'cells' => $cellTexts,
            ]);

            $buyPrice = $this->parseLocaleNumber($cellTexts[0] ?? '');
            $sellPrice = $this->parseLocaleNumber($cellTexts[1] ?? '');
            break;
        }

        if ($sellPrice === null) {
            throw new Exception('XAU satış fiyatı parse edilemedi');
        }

        return [
            'buy' => $buyPrice,
            'sell' => $sellPrice,
        ];
    }

    private function parseLocaleNumber(string $value): ?float
    {
        $value = trim(str_replace("\xc2\xa0", ' ', $value));
        $value = str_replace(' ', '', $value);

        if ($value === '') {
            return null;
        }

        $hasDot = str_contains($value, '.');
        $hasComma = str_contains($value, ',');

        if ($hasDot && $hasComma) {
            // TR format example: 5.921,50 -> 5921.50
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif ($hasComma && !$hasDot) {
            $value = str_replace(',', '.', $value);
        }

        $value = preg_replace('/[^0-9.\-]/', '', $value) ?? '';
        if ($value === '' || $value === '-' || $value === '.') {
            return null;
        }

        return (float) $value;
    }
}

