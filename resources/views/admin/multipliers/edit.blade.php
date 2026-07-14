@extends('layouts.app')

@section('title', 'Fiyat Marjları')

@php
    $instruments = ['HAS', 'ONS'];
    $sides = ['BID' => 'Alış', 'ASK' => 'Satış'];
@endphp

@section('content')
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Fiyat Marjları</h1>
            <p class="text-sm text-gray-600 mt-1">
                Mağaza: <span class="font-semibold">{{ $store->name }}</span>
            </p>
            <p class="text-xs text-gray-500 mt-1 max-w-2xl">
                Altın gram-içeriği sunucuda sabittir (çeyrek, yarım, tam…). Siz yalnızca
                <span class="font-medium">kendi farkınızı</span> girin: yüzde (%) ve/veya sabit tutar (₺).
                Fiyat = HAS × gram-sabiti × (1 + %marj) + tutar. Alış için eksi değer girebilirsiniz.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.stores.edit', $store) }}" class="text-sm text-gray-600 hover:text-gray-900 underline">Mağaza ayarları</a>
            <a href="{{ route('admin.stores.index') }}" class="text-sm text-gray-600 hover:text-gray-900 underline">Mağazalar</a>
        </div>
    </div>

    <div class="mt-4 bg-white rounded-lg shadow-md p-4">
        <form method="POST" action="{{ route('admin.stores.multipliers.update', $store) }}">
            @csrf

            <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
                <div class="text-xs text-gray-500">
                    Boş bıraktığınız hücreler kaydedilmez. (İstersen tüm hücreleri doldurup tek seferde bulk kaydedebilirsiniz.)
                </div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                    Kaydet
                </button>
            </div>

            <div class="overflow-auto">
                <table class="min-w-full text-xs">
                    <thead>
                    <tr class="bg-gray-50 text-gray-700">
                        <th class="py-2 px-3 text-left sticky left-0 bg-gray-50 z-10">Ürün</th>
                        @foreach($sources as $source)
                            <th class="py-2 px-3 text-center" colspan="{{ count($instruments) * count($sides) }}">
                                {{ $source->code }}
                                <div class="text-[10px] text-gray-500 font-normal">{{ $source->name }}</div>
                            </th>
                        @endforeach
                    </tr>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="py-2 px-3 text-left sticky left-0 bg-gray-50 z-10"></th>
                        @foreach($sources as $source)
                            @foreach($instruments as $instrument)
                                <th class="py-2 px-3 text-center" colspan="{{ count($sides) }}">{{ $instrument }}</th>
                            @endforeach
                        @endforeach
                    </tr>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="py-2 px-3 text-left sticky left-0 bg-gray-50 z-10"></th>
                        @foreach($sources as $source)
                            @foreach($instruments as $instrument)
                                @foreach($sides as $side => $sideLabel)
                                    <th class="py-2 px-3 text-center">{{ $sideLabel }}</th>
                                @endforeach
                            @endforeach
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($products as $product)
                        <tr class="border-t">
                            <td class="py-2 px-3 sticky left-0 bg-white z-10">
                                <div class="font-semibold text-gray-800">{{ $product->name }}</div>
                                <div class="text-[10px] text-gray-500">{{ $product->code }}</div>
                            </td>
                            @foreach($sources as $source)
                                @foreach($instruments as $instrument)
                                    @foreach($sides as $side => $sideLabel)
                                        @php
                                            $base = "multipliers.{$source->code}.{$instrument}.{$product->code}.{$side}";
                                            $name = "multipliers[{$source->code}][{$instrument}][{$product->code}][{$side}]";
                                            $cell = $values[$source->code][$instrument][$product->code][$side] ?? null;
                                            $pct = old("{$base}.margin_percent", $cell['margin_percent'] ?? '');
                                            $amt = old("{$base}.margin_amount", $cell['margin_amount'] ?? '');
                                            $active = old("{$base}.is_active", $cell['is_active'] ?? true);
                                        @endphp
                                        <td class="py-2 px-3 align-top">
                                            <div class="flex items-center gap-1">
                                                <input type="number" inputmode="decimal" step="0.01" min="-100" max="1000"
                                                    name="{{ $name }}[margin_percent]" value="{{ $pct }}"
                                                    class="w-16 border border-gray-300 rounded px-2 py-1 font-mono text-xs"
                                                    placeholder="%" title="Yüzde marj" />
                                                <span class="text-gray-400">%</span>
                                                <input type="number" inputmode="decimal" step="0.01"
                                                    name="{{ $name }}[margin_amount]" value="{{ $amt }}"
                                                    class="w-20 border border-gray-300 rounded px-2 py-1 font-mono text-xs"
                                                    placeholder="₺" title="Sabit tutar marj (₺)" />
                                            </div>
                                            <label class="mt-1 flex items-center gap-1 text-[10px] text-gray-600">
                                                <input type="checkbox" name="{{ $name }}[is_active]" value="1"
                                                    class="h-3 w-3" {{ $active ? 'checked' : '' }} />
                                                aktif
                                            </label>
                                        </td>
                                    @endforeach
                                @endforeach
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex items-center justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                    Kaydet
                </button>
            </div>
        </form>
    </div>
@endsection

