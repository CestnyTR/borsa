<?php

namespace App\Services;

use App\Models\GoldAsset;
use App\Models\GoldPrice;
use App\Models\GoldRawPrice;
use App\Models\GoldSource;
use App\Services\GoldScrapers\AhlatciDovizScraper;
use App\Services\GoldScrapers\GoldScraperInterface;
use App\Services\GoldScrapers\NadirDovizScraper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GoldPriceAggregator
{
    /**
     * @var array<GoldScraperInterface>
     */
    private array $scrapers;

    /**
     * @var array<string, float|null>
     */
    private array $lastBuyPrices = [];

    public function __construct()
    {
        $this->scrapers = [
            new NadirDovizScraper(),
            // AhlatciDovizScraper devre dışı: JSON verileri güncel değil (USD=7.51 eski veri)
            // new AhlatciDovizScraper(),
        ];
    }

    /**
     * Fetch prices from all sources
     *
     * @return array
     */
    public function fetchFromAllSources(): array
    {
        $asset = GoldAsset::where('code', 'GRAM_GOLD_TRY')->where('is_active', true)->first();

        if (!$asset) {
            throw new \Exception('Gold asset not found');
        }

        $results = [];
        $now = Carbon::now();

        $this->lastBuyPrices = [];

        foreach ($this->scrapers as $scraper) {
            try {
                $sourceCode = $scraper->getSourceCode();
                $source = GoldSource::where('code', $sourceCode)->where('is_active', true)->first();

                if (!$source) {
                    Log::warning("Source not found or inactive: {$sourceCode}");
                    continue;
                }

                $priceData = $scraper->fetchPrice();
                $this->lastBuyPrices[$sourceCode] = $priceData['buy'] ?? null;

                // Save raw price to database
                GoldRawPrice::create([
                    'source_id' => $source->id,
                    'asset_id' => $asset->id,
                    'buy_price' => $priceData['buy'],
                    'sell_price' => $priceData['sell'],
                    'fetched_at' => $now,
                ]);

                $results[$sourceCode] = $priceData['sell'];

                Log::info("Fetched price from {$sourceCode}: {$priceData['sell']}");
            } catch (\Exception $e) {
                Log::error("Failed to fetch from {$scraper->getSourceCode()}: {$e->getMessage()}");
            }
        }

        return $results;
    }

    /**
     * Aggregate prices and create official gold price
     *
     * @param array $rawPrices
     * @return GoldPrice
     */
    public function aggregateAndSave(array $rawPrices): GoldPrice
    {
        if (empty($rawPrices)) {
            throw new \Exception('No prices to aggregate');
        }

        $asset = GoldAsset::where('code', 'GRAM_GOLD_TRY')->first();

        // Calculate average price
        $avgPrice = array_sum($rawPrices) / count($rawPrices);
        $buyPrices = array_filter($this->lastBuyPrices, fn ($value) => $value !== null);
        $avgBuy = !empty($buyPrices) ? array_sum($buyPrices) / count($buyPrices) : null;

        // You can implement different strategies here:
        // - Average (current)
        // - Median
        // - Weighted average
        // - Priority-based (use Site1 first, fallback to Site2, etc.)

        $now = Carbon::now();

        // Invalidate previous prices
        GoldPrice::where('asset_id', $asset->id)
            ->whereNull('valid_to')
            ->update(['valid_to' => $now]);

        // Create new official price
        $goldPrice = GoldPrice::create([
            'asset_id' => $asset->id,
            'buy_price' => $avgBuy !== null ? round($avgBuy, 4) : null,
            'sell_price' => round($avgPrice, 4),
            'calculated_from' => [
                'sources' => array_keys($rawPrices),
                'method' => 'average',
                'raw_prices' => $rawPrices,
            ],
            'valid_from' => $now,
            'valid_to' => null,
        ]);

        Log::info("Created aggregated price: {$goldPrice->sell_price}");

        return $goldPrice;
    }

    /**
     * Fetch and aggregate in one go
     *
     * @return GoldPrice
     */
    public function fetchAndAggregate(): GoldPrice
    {
        $rawPrices = $this->fetchFromAllSources();
        return $this->aggregateAndSave($rawPrices);
    }
}
