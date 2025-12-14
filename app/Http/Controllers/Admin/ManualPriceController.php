<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoldAsset;
use App\Models\GoldPrice;
use App\Models\GoldRawPrice;
use App\Models\GoldSource;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ManualPriceController extends Controller
{
    /**
     * Manuel fiyat girişi için form
     */
    public function create()
    {
        $sources = GoldSource::where('is_active', true)->get();
        $latestPrices = GoldRawPrice::with('source')
            ->latest('fetched_at')
            ->take(5)
            ->get();

        return view('admin.prices.manual', compact('sources', 'latestPrices'));
    }

    /**
     * Manuel fiyat kaydet
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'source_code' => 'required|exists:gold_sources,code',
            'buy_price' => 'nullable|numeric|min:0',
            'sell_price' => 'required|numeric|min:0',
        ]);

        $source = GoldSource::where('code', $validated['source_code'])->first();
        $asset = GoldAsset::where('code', 'GRAM_GOLD_TRY')->first();

        // Raw price kaydet
        GoldRawPrice::create([
            'source_id' => $source->id,
            'asset_id' => $asset->id,
            'buy_price' => $validated['buy_price'],
            'sell_price' => $validated['sell_price'],
            'fetched_at' => now(),
        ]);

        // Aggregated price güncelle
        $this->updateAggregatedPrice($asset);

        return redirect()->route('admin.prices.create')
            ->with('success', 'Fiyat başarıyla kaydedildi!');
    }

    private function updateAggregatedPrice($asset)
    {
        // Son 5 dakikadaki fiyatların ortalamasını al
        $recentPrices = GoldRawPrice::where('asset_id', $asset->id)
            ->where('fetched_at', '>=', Carbon::now()->subMinutes(5))
            ->get();

        if ($recentPrices->isEmpty()) {
            return;
        }

        $avgPrice = $recentPrices->avg('sell_price');
        $now = Carbon::now();

        // Önceki fiyatı invalidate et
        GoldPrice::where('asset_id', $asset->id)
            ->whereNull('valid_to')
            ->update(['valid_to' => $now]);

        // Yeni aggregated price oluştur
        GoldPrice::create([
            'asset_id' => $asset->id,
            'buy_price' => null,
            'sell_price' => round($avgPrice, 4),
            'calculated_from' => [
                'sources' => $recentPrices->pluck('source.code')->unique()->values()->toArray(),
                'method' => 'average',
                'raw_prices' => $recentPrices->mapWithKeys(function ($price) {
                    return [$price->source->code => $price->sell_price];
                })->toArray(),
            ],
            'valid_from' => $now,
            'valid_to' => null,
        ]);
    }
}
