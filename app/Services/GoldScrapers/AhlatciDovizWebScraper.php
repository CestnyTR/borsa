<?php

namespace App\Services\GoldScrapers;

use Exception;
use Illuminate\Support\Facades\Log;

class AhlatciDovizWebScraper implements GoldScraperInterface
{
    private const URL = 'https://www.ahlatcidoviz.com.tr/kr.aspx';

    public function getSourceCode(): string
    {
        return 'AHLATCI_DOVIZ';
    }

    public function fetchPrice(): array
    {
        try {
            // HTML içeriğini çek
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

            Log::info('AhlatciDovizWebScraper: HTML fetched', [
                'size' => strlen($html),
            ]);

            return $this->parseHtml($html);

        } catch (Exception $e) {
            Log::error('AhlatciDovizWebScraper error: ' . $e->getMessage(), [
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

        // XAU satırını bul
        // Tablo yapısı: <tr><th>XAU</th><td>Alış</td><td>Satış</td></tr>
        $xauCells = $xpath->query("//th[contains(translate(normalize-space(text()), 'xau', 'XAU'), 'XAU')]");

        if ($xauCells->length === 0) {
            throw new Exception('XAU verisi bulunamadı');
        }

        $buyPrice = null;
        $sellPrice = null;

        // XAU satırını bulduk, şimdi aynı satırdaki diğer hücreleri al
        foreach ($xauCells as $xauCell) {
            $row = $xauCell->parentNode;

            // Satırdaki tüm td'leri al (th değil, sadece td - fiyat hücreleri)
            $cells = $xpath->query('.//td', $row);

            if ($cells->length >= 2) {
                // Format: <th>XAU</th><td>Alış</td><td>Satış</td>
                // cells[0] = Alış, cells[1] = Satış

                $cellTexts = [];
                foreach ($cells as $cell) {
                    $cellTexts[] = trim($cell->textContent);
                }

                Log::info('AhlatciDovizWebScraper: XAU row found', [
                    'cells' => $cellTexts,
                ]);

                // İlk iki hücre: Alış ve Satış
                if (count($cellTexts) >= 2) {
                    $buyPrice = $this->parseLocaleNumber($cellTexts[0]);
                    $sellPrice = $this->parseLocaleNumber($cellTexts[1]);
                    break; // Bulundu, döngüden çık
                }
            }
        }

        if ($sellPrice === null) {
            throw new Exception('XAU satış fiyatı parse edilemedi');
        }

        Log::info('AhlatciDovizWebScraper: Prices parsed (XAU)', [
            'buy' => $buyPrice,
            'sell' => $sellPrice,
        ]);

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
