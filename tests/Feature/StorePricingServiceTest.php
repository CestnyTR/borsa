<?php

namespace Tests\Feature;

use App\Models\MarketQuote;
use App\Models\Product;
use App\Models\Source;
use App\Models\Store;
use App\Models\StorePrice;
use App\Models\StorePriceMultiplier;
use App\Pricing\StorePricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StorePricingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_computes_and_upserts_store_prices_and_cache(): void
    {
        $store = Store::query()->create(['name' => 'S1', 'is_active' => true]);
        $source = Source::query()->create(['code' => 'HAREM', 'name' => 'Harem', 'is_active' => true]);
        // has_factor=2.0: fiyat = HAS × 2.0 × marj
        $product = Product::query()->create(['code' => 'GRAM_24', 'name' => 'Gram 24', 'has_factor' => 2.0]);

        MarketQuote::query()->create([
            'source_id' => $source->id,
            'instrument' => 'HAS',
            'bid' => 3000.0,
            'ask' => 3010.0,
            'currency' => 'TRY',
            'fetched_at' => now(),
            'raw_payload' => ['x' => 1],
        ]);

        // BID: %-1.5 marj, tutar yok.  ASK: %+2 marj + 50₺ tutar.
        StorePriceMultiplier::query()->create([
            'store_id' => $store->id,
            'source_id' => $source->id,
            'instrument' => 'HAS',
            'product_id' => $product->id,
            'side' => 'BID',
            'margin_percent' => -1.5,
            'margin_amount' => 0,
            'is_active' => true,
        ]);
        StorePriceMultiplier::query()->create([
            'store_id' => $store->id,
            'source_id' => $source->id,
            'instrument' => 'HAS',
            'product_id' => $product->id,
            'side' => 'ASK',
            'margin_percent' => 2.0,
            'margin_amount' => 50.0,
            'is_active' => true,
        ]);

        (new StorePricingService())->computeAndStore(cacheTtlSeconds: 90);

        $row = StorePrice::query()
            ->where('store_id', $store->id)
            ->where('source_id', $source->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertNotNull($row);
        // 3000 × 2.0 × (1 - 0.015) = 5910
        $this->assertSame(5910.0, round((float) $row->bid, 2));
        // 3010 × 2.0 × 1.02 + 50 = 6190.4
        $this->assertSame(6190.4, round((float) $row->ask, 2));

        $cached = Cache::get("store:prices:latest:{$store->id}:HAREM:GRAM_24");
        $this->assertIsArray($cached);
        $this->assertSame('GRAM_24', $cached['product']);
    }
}

