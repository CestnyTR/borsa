<?php

namespace App\Services\GoldScrapers;

use Exception;
use Illuminate\Support\Facades\Log;

class AhlatciDovizScraper implements GoldScraperInterface
{
    private const API_URL = 'https://www.ahlatcidoviz.com.tr/static/currencies.json';

    public function getSourceCode(): string
    {
        return 'AHLATCI_DOVIZ';
    }

    public function fetchPrice(): array
    {
        try {
            // JSON endpoint'ten veriyi çek
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        'Accept: application/json',
                    ],
                    'timeout' => 10,
                ],
            ]);

            $response = @file_get_contents(self::API_URL, false, $context);

            if ($response === false) {
                throw new Exception('Ahlatcı Döviz API\'ye erişilemedi');
            }

            $data = json_decode($response, true);

            if (!is_array($data)) {
                throw new Exception('Geçersiz JSON yanıtı');
            }

            Log::info('AhlatciDovizScraper: API response received', [
                'count' => count($data),
            ]);

            // XAU (TL cinsinden altın) verisini bul
            $xauData = null;
            foreach ($data as $item) {
                if (isset($item['SMB']) && $item['SMB'] === 'XAU') {
                    $xauData = $item;
                    break;
                }
            }

            if (!$xauData) {
                throw new Exception('XAU verisi bulunamadı');
            }

            // Fiyatları parse et
            $buyPrice = $this->parsePrice($xauData['Al'] ?? '');
            $sellPrice = $this->parsePrice($xauData['St'] ?? '');

            if ($buyPrice <= 0 || $sellPrice <= 0) {
                throw new Exception('Geçersiz fiyat değerleri');
            }

            // Fiyat aralığı kontrolü (makul değerler için)
            if ($buyPrice < 100 || $buyPrice > 100000 || $sellPrice < 100 || $sellPrice > 100000) {
                throw new Exception(sprintf(
                    'Fiyat aralığı dışında: Alış=%.2f, Satış=%.2f',
                    $buyPrice,
                    $sellPrice
                ));
            }

            Log::info('AhlatciDovizScraper: Prices parsed', [
                'buy' => $buyPrice,
                'sell' => $sellPrice,
            ]);

            return [
                'buy' => $buyPrice,
                'sell' => $sellPrice,
            ];

        } catch (Exception $e) {
            Log::error('AhlatciDovizScraper error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function parsePrice(string $priceStr): float
    {
        if (empty($priceStr)) {
            return 0.0;
        }

        // String olarak gelen sayıyı float'a çevir
        // Örnek: "414.65000" -> 414.65
        $price = (float) $priceStr;

        return round($price, 2);
    }
}
