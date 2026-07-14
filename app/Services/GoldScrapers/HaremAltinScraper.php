<?php

namespace App\Services\GoldScrapers;

use App\MarketData\Support\HaremSocketReader;
use App\MarketData\Support\NumberParser;
use Illuminate\Support\Facades\Log;

class HaremAltinScraper implements GoldScraperInterface
{
    public function __construct(
        private readonly HaremSocketReader $reader = new HaremSocketReader(),
    ) {
    }

    public function getSourceCode(): string
    {
        return 'HAREM_ALTIN';
    }

    /**
     * @return array{buy: float|null, sell: float}
     */
    public function fetchPrice(): array
    {
        try {
            // HAREM now publishes only over Socket.IO; see HaremSocketReader.
            $data = $this->reader->fetchSnapshot();

            Log::info('HaremAltinScraper: snapshot received', [
                'has_ons' => isset($data['ONS']),
            ]);

            return $this->parseResponse($data);
        } catch (\Exception $e) {
            Log::error('HaremAltinScraper error: ' . $e->getMessage(), [
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
        $onsData = HaremSocketReader::extractRow($data, ['ONS', 'XAUUSD'], ['ONS', 'XAUUSD', 'XAU/USD']);
        if ($onsData === null) {
            throw new \Exception('ONS price not found in API response');
        }

        // ONS prices are in USD (as shown on haremaltin.com)
        $buyUsd = isset($onsData['alis']) ? NumberParser::parseLocaleNumber($onsData['alis']) : null;
        $sellUsd = isset($onsData['satis']) ? NumberParser::parseLocaleNumber($onsData['satis']) : null;

        if ($sellUsd === null) {
            throw new \Exception('Sell price missing in API response');
        }

        Log::info('HaremAltinScraper: Prices parsed (USD)', [
            'buy' => $buyUsd,
            'sell' => $sellUsd,
        ]);

        return [
            'buy' => $buyUsd,
            'sell' => $sellUsd,
        ];
    }
}
