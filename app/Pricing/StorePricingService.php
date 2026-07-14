<?php

namespace App\Pricing;

use App\Events\MarketQuotesUpdated;
use App\Events\StorePricesUpdated;
use App\Models\MarketQuote;
use App\Models\Product;
use App\Models\Source;
use App\Models\Store;
use App\Models\StorePrice;
use App\Models\StorePriceMultiplier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StorePricingService
{
    public function __construct(
        private readonly PricingCalculator $calculator = new PricingCalculator(),
    ) {
    }

    public function computeAndStore(int $cacheTtlSeconds = 90): void
    {
        $latestQuotes = $this->latestQuotesBySourceAndInstrument();
        if ($latestQuotes->isEmpty()) {
            return;
        }

        $quotesPayload = $latestQuotes->map(fn (MarketQuote $quote) => [
            'id' => $quote->id,
            'source_id' => (int) $quote->source_id,
            'instrument' => (string) $quote->instrument,
            'bid' => $quote->bid !== null ? (float) $quote->bid : null,
            'ask' => $quote->ask !== null ? (float) $quote->ask : null,
            'currency' => (string) $quote->currency,
            'fetched_at' => $quote->fetched_at?->toIso8601String(),
        ])->values()->all();
        $lastQuoteAt = $latestQuotes->max(fn (MarketQuote $quote) => $quote->fetched_at?->getTimestamp() ?? 0);
        $lastQuoteIso = $lastQuoteAt > 0 ? CarbonImmutable::createFromTimestamp($lastQuoteAt)->toIso8601String() : null;

        $stores = Store::query()->where('is_active', true)->get();
        $products = Product::query()->get()->keyBy('id');
        $sourcesById = Source::query()->get()->keyBy('id');

        foreach ($stores as $store) {
            $multipliers = $this->activeMultipliersForStore($store->id);
            if ($multipliers === []) {
                continue;
            }

            $now = CarbonImmutable::now();
            $traceId = (string) Str::uuid();

            $cacheBySource = [];
            $pricesPayload = [];

            foreach ($latestQuotes as $quote) {
                if ((string) $quote->instrument !== 'HAS') {
                    continue;
                }

                $sourceId = (int) $quote->source_id;
                $sourceCode = (string) ($sourcesById[$sourceId]->code ?? $sourceId);

                $cacheBySource[$sourceCode] ??= [
                    'store_id' => $store->id,
                    'source' => $sourceCode,
                    'calculated_at' => $now->toIso8601String(),
                    'products' => [],
                ];

                $instrument = (string) $quote->instrument;

                foreach ($multipliers[$sourceId][$instrument] ?? [] as $productId => $sides) {
                    $bidMargin = $sides['BID'] ?? null;
                    $askMargin = $sides['ASK'] ?? null;

                    // Gram-içeriği ürün sabiti (sunucu); marj mağazadan gelir.
                    $hasFactor = isset($products[$productId]) ? (float) $products[$productId]->has_factor : null;

                    $bid = $this->calculator->compute(
                        $quote->bid !== null ? (float) $quote->bid : null,
                        $hasFactor,
                        $bidMargin['percent'] ?? 0.0,
                        $bidMargin['amount'] ?? 0.0
                    );
                    $ask = $this->calculator->compute(
                        $quote->ask !== null ? (float) $quote->ask : null,
                        $hasFactor,
                        $askMargin['percent'] ?? 0.0,
                        $askMargin['amount'] ?? 0.0
                    );

                    $basis = [
                        'market_quote_id' => $quote->id,
                        'source_id' => $sourceId,
                        'instrument' => $instrument,
                        'has_factor' => $hasFactor,
                        'raw' => [
                            'bid' => $quote->bid !== null ? (float) $quote->bid : null,
                            'ask' => $quote->ask !== null ? (float) $quote->ask : null,
                            'currency' => (string) $quote->currency,
                            'fetched_at' => $quote->fetched_at?->toIso8601String(),
                        ],
                        'margins' => [
                            'BID' => $bidMargin,
                            'ASK' => $askMargin,
                        ],
                    ];

                    StorePrice::query()->updateOrCreate(
                        [
                            'store_id' => $store->id,
                            'source_id' => $sourceId,
                            'product_id' => $productId,
                        ],
                        [
                            'bid' => $bid,
                            'ask' => $ask,
                            'calculated_at' => $now,
                            'basis' => $basis,
                        ]
                    );

                    $product = $products[$productId] ?? null;
                    $productCode = $product?->code ?? (string) $productId;

                    $cacheBySource[$sourceCode]['products'][$productCode] = [
                        'bid' => $bid,
                        'ask' => $ask,
                        'basis' => $basis,
                    ];

                    $pricesPayload[] = [
                        'product_id' => $productId,
                        'source_id' => $sourceId,
                        'instrument' => $instrument,
                        'bid' => $bid,
                        'ask' => $ask,
                        'computed_price' => $ask ?? $bid,
                        'updated_at' => $now->toIso8601String(),
                    ];

                    Cache::put(
                        $this->cacheKeyLatestProduct($store->id, $sourceCode, $productCode),
                        [
                            'store_id' => $store->id,
                            'source' => $sourceCode,
                            'product' => $productCode,
                            'bid' => $bid,
                            'ask' => $ask,
                            'calculated_at' => $now->toIso8601String(),
                            'basis' => $basis,
                        ],
                        $cacheTtlSeconds
                    );
                }
            }

            foreach ($cacheBySource as $sourceCode => $payload) {
                Cache::put(
                    $this->cacheKeyLatestSource($store->id, $sourceCode),
                    $payload,
                    $cacheTtlSeconds
                );
            }

            if ($pricesPayload !== []) {
                Store::query()->where('id', $store->id)->update(['last_priced_at' => $now]);

                event(new StorePricesUpdated(
                    storeId: $store->id,
                    prices: $pricesPayload,
                    meta: [
                        'emitted_at' => $now->toIso8601String(),
                        'source_ids' => array_values(array_unique(array_column($pricesPayload, 'source_id'))),
                        'store_id' => $store->id,
                        'trace_id' => $traceId,
                        'last_update_at' => $now->toIso8601String(),
                    ]
                ));

                event(new MarketQuotesUpdated(
                    quotes: $quotesPayload,
                    meta: [
                        'emitted_at' => $now->toIso8601String(),
                        'source_ids' => array_values(array_unique(array_column($quotesPayload, 'source_id'))),
                        'store_id' => $store->id,
                        'trace_id' => $traceId,
                        'last_update_at' => $lastQuoteIso,
                    ],
                    channelNames: ["store.{$store->id}"]
                ));
            }
        }
    }

    /**
     * @return Collection<int, MarketQuote>
     */
    private function latestQuotesBySourceAndInstrument(): Collection
    {
        $latestIds = MarketQuote::query()
            ->select('source_id', 'instrument', DB::raw('MAX(id) as max_id'))
            ->groupBy('source_id', 'instrument');

        return MarketQuote::query()
            ->joinSub($latestIds, 'latest', function ($join) {
                $join->on('market_quotes.id', '=', 'latest.max_id');
            })
            ->orderBy('source_id')
            ->orderBy('instrument')
            ->get(['market_quotes.*']);
    }

    /**
     * @return array<int, array<string, array<int, array{BID?: float, ASK?: float}>>>
     */
    private function activeMultipliersForStore(int $storeId): array
    {
        $rows = StorePriceMultiplier::query()
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $sourceId = (int) $row->source_id;
            $instrument = (string) $row->instrument;
            $productId = (int) $row->product_id;
            $side = (string) $row->side;

            $grouped[$sourceId] ??= [];
            $grouped[$sourceId][$instrument] ??= [];
            $grouped[$sourceId][$instrument][$productId] ??= [];
            $grouped[$sourceId][$instrument][$productId][$side] = [
                'percent' => (float) $row->margin_percent,
                'amount' => (float) $row->margin_amount,
            ];
        }

        return $grouped;
    }

    public function cacheKeyLatestSource(int $storeId, string $sourceCode): string
    {
        return "store:prices:latest:{$storeId}:{$sourceCode}";
    }

    public function cacheKeyLatestProduct(int $storeId, string $sourceCode, string $productCode): string
    {
        return "store:prices:latest:{$storeId}:{$sourceCode}:{$productCode}";
    }
}
