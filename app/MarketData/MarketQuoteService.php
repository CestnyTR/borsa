<?php

namespace App\MarketData;

use App\MarketData\Clients\MarketDataClientFactory;
use App\Models\MarketQuote;
use App\Models\Source;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MarketQuoteService
{
    public function __construct(
        private readonly MarketDataClientFactory $clientFactory = new MarketDataClientFactory(),
    ) {
    }

    public function fetchAndStoreActiveSources(int $cacheTtlSeconds = 90): void
    {
        $sources = Source::query()->where('is_active', true)->get();

        foreach ($sources as $source) {
            try {
                $client = $this->clientFactory->make($source->code);
                $quotes = $client->fetchQuotes();

                foreach ($quotes as $quote) {
                    $model = MarketQuote::query()->create([
                        'source_id' => $source->id,
                        'instrument' => $quote->instrument,
                        'bid' => $quote->bid,
                        'ask' => $quote->ask,
                        'currency' => $quote->currency,
                        'fetched_at' => $quote->fetchedAt,
                        'raw_payload' => $quote->rawPayload,
                    ]);

                    Cache::put(
                        $this->cacheKeyLatest($source->code, $quote->instrument),
                        [
                            'id' => $model->id,
                            'source' => $source->code,
                            'instrument' => $quote->instrument,
                            'source_instrument' => $this->sourceInstrumentLabel($quote->instrument, $quote->rawPayload),
                            'bid' => $quote->bid,
                            'ask' => $quote->ask,
                            'currency' => $quote->currency,
                            'fetched_at' => $quote->fetchedAt->toIso8601String(),
                        ],
                        $cacheTtlSeconds
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('MarketQuoteService: fetch failed', [
                    'source' => $source->code,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    public function cacheKeyLatest(string $sourceCode, string $instrument): string
    {
        return "market:latest:{$sourceCode}:{$instrument}";
    }

    /**
     * @param array<string, mixed>|null $rawPayload
     */
    private function sourceInstrumentLabel(string $instrument, ?array $rawPayload): string
    {
        if (is_array($rawPayload)) {
            $smb = $rawPayload['SMB'] ?? null;
            if (is_string($smb) && $smb !== '') {
                return $smb;
            }

            $parite = $rawPayload['parite'] ?? null;
            if (is_string($parite) && $parite !== '') {
                return $parite;
            }

            $baslik = $rawPayload['baslik'] ?? null;
            if (is_string($baslik) && $baslik !== '') {
                return $baslik;
            }
        }

        return $instrument;
    }
}
