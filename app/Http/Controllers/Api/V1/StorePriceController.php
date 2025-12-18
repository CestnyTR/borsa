<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Source;
use App\Models\Store;
use App\Models\StorePrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StorePriceController extends Controller
{
    public function latest(Request $request, Store $store): JsonResponse
    {
        $sourceCode = $request->query('source');
        $sourceCodes = $sourceCode ? [strtoupper((string) $sourceCode)] : null;

        if ($sourceCodes !== null) {
            $payload = Cache::get("store:prices:latest:{$store->id}:{$sourceCodes[0]}");
            if (is_array($payload)) {
                return response()->json(['data' => [$sourceCodes[0] => $payload]]);
            }
        }

        $query = StorePrice::query()
            ->where('store_id', $store->id)
            ->with([
                'source:id,code,name',
                'product:id,code,name',
            ]);

        if ($sourceCodes !== null) {
            $source = Source::query()->where('code', $sourceCodes[0])->firstOrFail();
            $query->where('source_id', $source->id);
        }

        $rows = $query->get();

        $result = [];
        foreach ($rows as $row) {
            $sourceKey = $row->source->code;
            $productKey = $row->product->code;

            $result[$sourceKey] ??= [
                'store_id' => $store->id,
                'source' => $sourceKey,
                'calculated_at' => $row->calculated_at?->toIso8601String(),
                'products' => [],
            ];

            $result[$sourceKey]['products'][$productKey] = [
                'bid' => $row->bid !== null ? (float) $row->bid : null,
                'ask' => $row->ask !== null ? (float) $row->ask : null,
                'basis' => $row->basis,
            ];
        }

        return response()->json([
            'data' => [
                'store' => [
                    'id' => $store->id,
                    'name' => $store->name,
                ],
                'sources' => $result,
            ],
        ]);
    }
}

