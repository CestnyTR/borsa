<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Source;
use App\Models\Store;
use App\Models\StorePriceMultiplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MultiplierController extends Controller
{
    public function upsert(Request $request, Store $store): JsonResponse
    {
        $data = $request->validate([
            'multipliers' => ['required', 'array', 'min:1'],
            'multipliers.*.source' => ['required', 'string', Rule::exists('sources', 'code')],
            'multipliers.*.instrument' => ['required', Rule::in(['HAS', 'ONS'])],
            'multipliers.*.product' => ['required', 'string', Rule::exists('products', 'code')],
            'multipliers.*.side' => ['required', Rule::in(['BID', 'ASK'])],
            'multipliers.*.margin_percent' => ['sometimes', 'numeric', 'min:-100', 'max:1000'],
            'multipliers.*.margin_amount' => ['sometimes', 'numeric'],
            'multipliers.*.is_active' => ['sometimes', 'boolean'],
        ]);

        $count = 0;

        foreach ($data['multipliers'] as $item) {
            $source = Source::query()->where('code', strtoupper($item['source']))->firstOrFail();
            $product = Product::query()->where('code', strtoupper($item['product']))->firstOrFail();

            StorePriceMultiplier::query()->updateOrCreate(
                [
                    'store_id' => $store->id,
                    'source_id' => $source->id,
                    'instrument' => $item['instrument'],
                    'product_id' => $product->id,
                    'side' => $item['side'],
                ],
                [
                    'margin_percent' => $item['margin_percent'] ?? 0,
                    'margin_amount' => $item['margin_amount'] ?? 0,
                    'is_active' => $item['is_active'] ?? true,
                ]
            );

            $count++;
        }

        return $this->apiSuccess([
            'upserted' => $count,
        ]);
    }
}
