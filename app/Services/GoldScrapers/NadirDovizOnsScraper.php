<?php

namespace App\Services\GoldScrapers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class NadirDovizOnsScraper implements GoldScraperInterface
{
    private const API_URL = 'https://www.nadirdoviz.com/api/KurOku';
    private const API_KEY = '1397x2684y3971z6842';
    private const API_GROUP_ID = 1;

    /**
     * API listesinden Altın/ONS satırını çekiyoruz.
     */
    private const TARGET_PARITES = [
        'XAU/USD',
    ];

    private const TARGET_BASLIKS = [
        'ALTIN/ONS',
        'ALTIN ONS',
        'ALTIN / ONS',
    ];

    public function getSourceCode(): string
    {
        return 'NADIR_DOVIZ_ONS';
    }

    public function fetchPrice(): array
    {
        $payload = [
            'grupId' => self::API_GROUP_ID,
            'kayitSayisi' => null,
            'grupListYukle' => false,
            'ilkGrupKurlariGetir' => false,
            'anahtar' => self::API_KEY,
            'bolgeMarjListesiniBellegeYukle' => false,
            'sessionId' => '',
        ];

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json, text/plain, */*',
                'Origin' => 'https://www.nadirdoviz.com',
                'Referer' => 'https://www.nadirdoviz.com/fiyat-ekrani',
                'User-Agent' => config('app.scraper_user_agent', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'),
                'X-Requested-With' => 'XMLHttpRequest',
            ])->timeout(15)->post(self::API_URL, $payload);

            if (!$response->successful()) {
                throw new \Exception('API request failed with status ' . $response->status());
            }

            $data = $response->json();

            Log::info('NadirDovizOnsScraper: API response received', [
                'count' => is_array($data['list'] ?? null) ? count($data['list']) : 0,
            ]);

            return $this->parseResponse($data);
        } catch (\Exception $e) {
            Log::error('NadirDovizOnsScraper API error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array{buy: float|null, sell: float}
     */
    private function parseResponse(array $data): array
    {
        if (!isset($data['list']) || !is_array($data['list']) || empty($data['list'])) {
            throw new \Exception('Invalid API response: price list missing');
        }

        $targetRow = $this->findOnsGoldRow($data['list']);

        if (!$targetRow) {
            throw new \Exception('Ons gold price entry not found in API response');
        }

        $buy = isset($targetRow['alis']) ? (float) $targetRow['alis'] : null;
        $sell = isset($targetRow['satis']) ? (float) $targetRow['satis'] : null;

        if ($sell === null) {
            throw new \Exception('Sell price missing in API response');
        }

        return [
            'buy' => $buy,
            'sell' => $sell,
        ];
    }

    /**
     * @param array<int, mixed> $list
     * @return array<string, mixed>|null
     */
    private function findOnsGoldRow(array $list): ?array
    {
        foreach ($list as $row) {
            if (!is_array($row)) {
                continue;
            }

            $parite = strtoupper((string) ($row['parite'] ?? ''));
            $baslik = Str::upper(Str::of((string) ($row['baslik'] ?? ''))
                ->replace('İ', 'I')
                ->replace('ı', 'I')
                ->value());

            if (
                in_array($parite, self::TARGET_PARITES, true) ||
                in_array($baslik, self::TARGET_BASLIKS, true)
            ) {
                return $row;
            }
        }

        return null;
    }
}
