<?php

namespace App\Services;

use App\Events\CompanyGoldUpdated;
use App\Events\GoldPricesUpdated;
use App\Models\Company;
use App\Models\GoldAsset;
use App\Models\GoldPrice;
use App\Models\GoldRawPrice;
use App\Models\GoldSource;
use App\Services\GoldScrapers\AhlatciDovizGramScraper;
use App\Services\GoldScrapers\GoldScraperInterface;
use App\Services\GoldScrapers\HaremAltinGramScraper;
use App\Services\GoldScrapers\NadirDovizScraper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            new AhlatciDovizGramScraper(),
            new HaremAltinGramScraper(),
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

        $goldSources = GoldSource::query()->where('is_active', true)->get()->keyBy('code');
        $orderedScrapers = $this->orderScrapersByHealth($goldSources->all());

        foreach ($orderedScrapers as $scraper) {
            try {
                $startedAt = microtime(true);
                $sourceCode = $scraper->getSourceCode();
                $source = $goldSources->get($sourceCode);

                if (!$source) {
                    Log::warning("Source not found or inactive: {$sourceCode}");
                    continue;
                }

                $priceData = $scraper->fetchPrice();
                $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
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

                app(SourceHealthService::class)->recordSuccess('gold', $source->id, $latencyMs);
                Log::info("Fetched price from {$sourceCode}: {$priceData['sell']}");
            } catch (\Exception $e) {
                $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
                if (isset($source) && $source) {
                    app(SourceHealthService::class)->recordFailure('gold', $source->id, $latencyMs, $e->getMessage());
                }
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

        $asset->update(['last_price_at' => $now]);

        $traceId = (string) Str::uuid();

        $sourceIds = GoldSource::query()
            ->whereIn('code', array_keys($rawPrices))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        event(new GoldPricesUpdated(
            assetId: (int) $asset->id,
            sellPrice: (float) $goldPrice->sell_price,
            buyPrice: $goldPrice->buy_price !== null ? (float) $goldPrice->buy_price : null,
            validFrom: $goldPrice->valid_from->toIso8601String(),
            sourceIds: $sourceIds,
            traceId: $traceId,
            lastUpdateAt: $now->toIso8601String()
        ));

        $companyService = app(CompanyGoldPriceService::class);
        $companies = Company::query()->where('is_active', true)->get(['id']);
        foreach ($companies as $company) {
            try {
                Company::query()->where('id', $company->id)->update(['last_gold_prices_at' => $now]);
                $payload = $companyService->buildCompanyBroadcastPayload((int) $company->id, $now, $traceId);
                event(new CompanyGoldUpdated($payload['company_id'], $payload['items'], $payload['meta']));
            } catch (\Throwable $e) {
                Log::warning('Company gold broadcast skipped', [
                    'company_id' => $company->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

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

    /**
     * @param array<string, GoldSource> $goldSources
     * @return array<GoldScraperInterface>
     */
    private function orderScrapersByHealth(array $goldSources): array
    {
        if ($goldSources === []) {
            return $this->scrapers;
        }

        $ids = array_map(fn (GoldSource $source) => (int) $source->id, $goldSources);
        $orderedIds = app(SourceHealthService::class)->orderSourceIds('gold', $ids);
        $orderIndex = array_flip($orderedIds);

        $ordered = $this->scrapers;
        usort($ordered, function (GoldScraperInterface $a, GoldScraperInterface $b) use ($goldSources, $orderIndex): int {
            $sourceA = $goldSources[$a->getSourceCode()] ?? null;
            $sourceB = $goldSources[$b->getSourceCode()] ?? null;

            $rankA = $sourceA ? ($orderIndex[$sourceA->id] ?? PHP_INT_MAX) : PHP_INT_MAX;
            $rankB = $sourceB ? ($orderIndex[$sourceB->id] ?? PHP_INT_MAX) : PHP_INT_MAX;

            if ($rankA === $rankB) {
                return strcmp($a->getSourceCode(), $b->getSourceCode());
            }

            return $rankA <=> $rankB;
        });

        return $ordered;
    }
}
