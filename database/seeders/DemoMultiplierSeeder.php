<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Source;
use App\Models\Store;
use App\Models\StorePriceMultiplier;
use Illuminate\Database\Seeder;

class DemoMultiplierSeeder extends Seeder
{
    public function run(): void
    {
        $store = Store::query()->orderBy('id')->first();
        if (!$store) {
            return;
        }

        $exists = StorePriceMultiplier::query()
            ->where('store_id', $store->id)
            ->exists();

        if ($exists) {
            return;
        }

        $sources = Source::query()->where('is_active', true)->get();
        $products = Product::query()->get();

        foreach ($sources as $source) {
            foreach ($products as $product) {
                foreach (['BID', 'ASK'] as $side) {
                    StorePriceMultiplier::query()->updateOrCreate(
                        [
                            'store_id' => $store->id,
                            'source_id' => $source->id,
                            'instrument' => 'HAS',
                            'product_id' => $product->id,
                            'side' => $side,
                        ],
                        [
                            'multiplier' => 1.0,
                            'is_active' => true,
                        ]
                    );
                }
            }
        }
    }
}

