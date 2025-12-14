<?php

namespace App\Services\GoldScrapers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HaremAltinGramScraper implements GoldScraperInterface
{
    private const API_URL = 'https://canlipiyasalar.haremaltin.com/tmp/altin.json';

    public function getSourceCode(): string
    {
        return 'HAREM_ALTIN';
    }

    public function fetchPrice(): array
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json, text/plain, */*',
                'Referer' => 'https://canlipiyasalar.haremaltin.com/',
                'User-Agent' => config('app.scraper_user_agent', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'),
            ])->timeout(15)->get(self::API_URL, [
                'dil_kodu' => 'tr',
            ]);

            if (!$response->successful()) {
                throw new \Exception('API request failed with status ' . $response->status());
            }

            $data = $response->json();

            Log::info('HaremAltinGramScraper: API response received', [
                'has_data' => isset($data['data']),
            ]);

            return $this->parseResponse($data);
        } catch (\Exception $e) {
            Log::error('HaremAltinGramScraper API error: ' . $e->getMessage(), [
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
        if (!isset($data['data']) || !is_array($data['data'])) {
            throw new \Exception('Invalid API response: data missing');
        }

        $hasAltin = $this->extractHasAltinRow($data['data']);
        if (!$hasAltin) {
            throw new \Exception('HAS ALTIN price not found in API response');
        }

        $buy = array_key_exists('alis', $hasAltin) ? $this->parseLocaleNumber($hasAltin['alis']) : null;
        $sell = array_key_exists('satis', $hasAltin) ? $this->parseLocaleNumber($hasAltin['satis']) : null;

        if ($sell === null) {
            throw new \Exception('Sell price missing in API response');
        }

        return [
            'buy' => $buy,
            'sell' => $sell,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    private function extractHasAltinRow(array $data): ?array
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
            if ($normalizedKey !== 'HASALTIN') {
                continue;
            }

            return $value;
        }

        return null;
    }

    /**
     * @param mixed $value
     */
    private function parseLocaleNumber($value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim(str_replace("\xc2\xa0", ' ', $value));
        $value = str_replace(' ', '', $value);

        if ($value === '') {
            return null;
        }

        $hasDot = str_contains($value, '.');
        $hasComma = str_contains($value, ',');

        if ($hasDot && $hasComma) {
            // TR format example: 5.923,01 -> 5923.01
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

