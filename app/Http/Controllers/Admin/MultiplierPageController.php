<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
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
                'margin_percent' => (float) $row->margin_percent,
                'margin_amount' => (float) $row->margin_amount,
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

                        $percentRaw = $cell['margin_percent'] ?? null;
                        $amountRaw = $cell['margin_amount'] ?? null;

                        // İkisi de boşsa bu hücre atlanır.
                        if (($percentRaw === '' || $percentRaw === null) && ($amountRaw === '' || $amountRaw === null)) {
                            continue;
                        }

                        if (($percentRaw !== '' && $percentRaw !== null && !is_numeric($percentRaw))
                            || ($amountRaw !== '' && $amountRaw !== null && !is_numeric($amountRaw))) {
                            return back()
                                ->withErrors(['multipliers' => "{$sourceCode}/{$instrument}/{$productCode}/{$side} için marj sayı olmalı."])
                                ->withInput();
                        }

                        $percent = ($percentRaw === '' || $percentRaw === null) ? 0.0 : (float) $percentRaw;
                        $amount = ($amountRaw === '' || $amountRaw === null) ? 0.0 : (float) $amountRaw;

                        if ($percent < -100 || $percent > 1000) {
                            return back()
                                ->withErrors(['multipliers' => "{$sourceCode}/{$instrument}/{$productCode}/{$side} için yüzde marj aralık dışı."])
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
                                'margin_percent' => $percent,
                                'margin_amount' => $amount,
                                'is_active' => $isActive,
                            ]
                        );

                        $upserted++;
                    }
                }
            }
        }

        AuditLog::record('multipliers.updated', $store, ['cells' => $upserted]);

        return redirect()
            ->route('admin.stores.multipliers.edit', $store)
            ->with('success', "{$upserted} hücre kaydedildi.");
    }
}
