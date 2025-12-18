<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Source;
use App\Models\Store;
use App\Models\StorePriceMultiplier;
use Illuminate\Http\Request;

class MultiplierPageController extends Controller
{
    public function edit(Store $store)
    {
        $sources = Source::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
        $products = Product::query()->orderBy('code')->get(['id', 'code', 'name']);

        $rows = StorePriceMultiplier::query()
            ->where('store_id', $store->id)
            ->with(['source:id,code', 'product:id,code'])
            ->get();

        $values = [];
        foreach ($rows as $row) {
            $values[$row->source->code][(string) $row->instrument][$row->product->code][(string) $row->side] = [
                'multiplier' => $row->multiplier !== null ? (float) $row->multiplier : null,
                'is_active' => (bool) $row->is_active,
            ];
        }

        return view('admin.multipliers.edit', compact('store', 'sources', 'products', 'values'));
    }

    public function update(Request $request, Store $store)
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->input('multipliers', []);
        if (!is_array($payload)) {
            return back()->withErrors(['multipliers' => 'Geçersiz payload.'])->withInput();
        }

        $sources = Source::query()->where('is_active', true)->get(['id', 'code'])->keyBy('code');
        $products = Product::query()->get(['id', 'code'])->keyBy('code');

        $upserted = 0;

        foreach ($payload as $sourceCode => $byInstrument) {
            $sourceCode = strtoupper((string) $sourceCode);
            $source = $sources->get($sourceCode);
            if (!$source || !is_array($byInstrument)) {
                continue;
            }

            foreach ($byInstrument as $instrument => $byProduct) {
                $instrument = strtoupper((string) $instrument);
                if (!in_array($instrument, ['HAS', 'ONS'], true) || !is_array($byProduct)) {
                    continue;
                }

                foreach ($byProduct as $productCode => $bySide) {
                    $productCode = strtoupper((string) $productCode);
                    $product = $products->get($productCode);
                    if (!$product || !is_array($bySide)) {
                        continue;
                    }

                    foreach ($bySide as $side => $cell) {
                        $side = strtoupper((string) $side);
                        if (!in_array($side, ['BID', 'ASK'], true) || !is_array($cell)) {
                            continue;
                        }

                        $multiplierRaw = $cell['multiplier'] ?? null;
                        if ($multiplierRaw === '' || $multiplierRaw === null) {
                            continue;
                        }

                        if (!is_numeric($multiplierRaw)) {
                            return back()
                                ->withErrors(['multipliers' => "{$sourceCode}/{$instrument}/{$productCode}/{$side} için multiplier sayı olmalı."])
                                ->withInput();
                        }

                        $multiplier = (float) $multiplierRaw;
                        if ($multiplier < 0.000001 || $multiplier > 9999) {
                            return back()
                                ->withErrors(['multipliers' => "{$sourceCode}/{$instrument}/{$productCode}/{$side} için multiplier aralık dışı."])
                                ->withInput();
                        }

                        $isActive = array_key_exists('is_active', $cell) ? (bool) $cell['is_active'] : false;

                        StorePriceMultiplier::query()->updateOrCreate(
                            [
                                'store_id' => $store->id,
                                'source_id' => $source->id,
                                'instrument' => $instrument,
                                'product_id' => $product->id,
                                'side' => $side,
                            ],
                            [
                                'multiplier' => $multiplier,
                                'is_active' => $isActive,
                            ]
                        );

                        $upserted++;
                    }
                }
            }
        }

        return redirect()
            ->route('admin.stores.multipliers.edit', $store)
            ->with('success', "{$upserted} hücre kaydedildi.");
    }
}
