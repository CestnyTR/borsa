<?php

namespace App\Services\GoldScrapers;

use App\MarketData\Support\HaremSocketReader;
use App\MarketData\Support\NumberParser;
use Illuminate\Support\Facades\Log;

class HaremAltinGramScraper implements GoldScraperInterface
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

            Log::info('HaremAltinGramScraper: snapshot received', [
                'has_altin' => isset($data['ALTIN']),
            ]);

            return $this->parseResponse($data);
        } catch (\Exception $e) {
            Log::error('HaremAltinGramScraper error: ' . $e->getMessage(), [
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
        // Gram gold ("HAS Altın") is published under the ALTIN key in TRY.
        $hasAltin = HaremSocketReader::extractRow($data, ['ALTIN', 'HASALTIN', 'HAS_ALTIN', 'HAS'], ['ALTIN', 'HASALTIN', 'HAS']);
        if ($hasAltin === null) {
            throw new \Exception('HAS ALTIN price not found in API response');
        }

        $buy = array_key_exists('alis', $hasAltin) ? NumberParser::parseLocaleNumber($hasAltin['alis']) : null;
        $sell = array_key_exists('satis', $hasAltin) ? NumberParser::parseLocaleNumber($hasAltin['satis']) : null;

        if ($sell === null) {
            throw new \Exception('Sell price missing in API response');
        }

        return [
            'buy' => $buy,
            'sell' => $sell,
        ];
    }
}
