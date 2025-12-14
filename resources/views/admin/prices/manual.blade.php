<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manuel Fiyat Girişi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <nav class="bg-white shadow-md">
            <div class="container mx-auto px-4 py-4">
                <div class="flex space-x-4">
                    <a href="/" class="text-gray-600 hover:text-gray-900">Dashboard</a>
                    <a href="{{ route('admin.companies.index') }}" class="text-gray-600 hover:text-gray-900">Şirketler</a>
                    <a href="{{ route('admin.prices.create') }}" class="text-blue-600 font-semibold">Manuel Fiyat</a>
                </div>
            </div>
        </nav>

        <div class="container mx-auto px-4 py-8 max-w-4xl">
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Form -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h1 class="text-2xl font-bold mb-4">Manuel Fiyat Girişi</h1>
                    <p class="text-gray-600 mb-6">Nadirdoviz.com'dan aldığınız fiyatı buraya girin</p>

                    <form action="{{ route('admin.prices.store') }}" method="POST">
                        @csrf

                        <div class="mb-4">
                            <label class="block text-gray-700 font-semibold mb-2">Kaynak *</label>
                            <select name="source_code" required class="w-full border border-gray-300 rounded-lg px-4 py-2">
                                @foreach($sources as $source)
                                    <option value="{{ $source->code }}" {{ $source->code === 'NADIR_DOVIZ' ? 'selected' : '' }}>
                                        {{ $source->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('source_code')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="block text-gray-700 font-semibold mb-2">Alış Fiyatı (TL)</label>
                            <input type="number" step="0.01" name="buy_price"
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2"
                                   placeholder="ör: 5807.88"
                                   value="{{ old('buy_price') }}">
                            @error('buy_price')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-6">
                            <label class="block text-gray-700 font-semibold mb-2">Satış Fiyatı (TL) *</label>
                            <input type="number" step="0.01" name="sell_price" required
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2"
                                   placeholder="ör: 5824.51"
                                   value="{{ old('sell_price') }}">
                            @error('sell_price')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold">
                            Fiyatı Kaydet
                        </button>
                    </form>

                    <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                        <h3 class="font-semibold text-blue-800 mb-2">💡 Hızlı Erişim</h3>
                        <a href="https://www.nadirdoviz.com/fiyat-ekrani" target="_blank"
                           class="text-blue-600 hover:underline text-sm">
                            Nadirdoviz.com Fiyat Ekranı →
                        </a>
                    </div>
                </div>

                <!-- Son Girilen Fiyatlar -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-bold mb-4">Son Girilen Fiyatlar</h2>

                    @if($latestPrices->isEmpty())
                        <p class="text-gray-500 text-center py-8">Henüz fiyat girilmemiş</p>
                    @else
                        <div class="space-y-3">
                            @foreach($latestPrices as $price)
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex justify-between items-start mb-2">
                                        <span class="font-semibold text-gray-800">{{ $price->source->name }}</span>
                                        <span class="text-xs text-gray-500">{{ $price->fetched_at->diffForHumans() }}</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        @if($price->buy_price)
                                            <div>
                                                <span class="text-gray-600">Alış:</span>
                                                <span class="font-semibold">{{ number_format($price->buy_price, 2) }} TL</span>
                                            </div>
                                        @endif
                                        <div>
                                            <span class="text-gray-600">Satış:</span>
                                            <span class="font-semibold">{{ number_format($price->sell_price, 2) }} TL</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</body>
</html>
