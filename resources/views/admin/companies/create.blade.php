<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Şirket Ekle</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <nav class="bg-white shadow-md">
            <div class="container mx-auto px-4 py-4">
                <a href="{{ route('admin.companies.index') }}" class="text-blue-600 hover:text-blue-800">← Geri Dön</a>
            </div>
        </nav>

        <div class="container mx-auto px-4 py-8 max-w-2xl">
            <h1 class="text-3xl font-bold mb-6">Yeni Şirket Ekle</h1>

            <form action="{{ route('admin.companies.store') }}" method="POST" class="bg-white rounded-lg shadow-md p-6">
                @csrf

                <div class="mb-4">
                    <label class="block text-gray-700 font-semibold mb-2">Şirket Adı *</label>
                    <input type="text" name="name" required
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500"
                           placeholder="ör: Kuyumcu A"
                           value="{{ old('name') }}">
                    @error('name')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 font-semibold mb-2">Şirket Kodu *</label>
                    <input type="text" name="code" required
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500"
                           placeholder="ör: KUYUMCU_A"
                           value="{{ old('code') }}">
                    <p class="text-gray-500 text-sm mt-1">Benzersiz bir kod olmalı (boşluk yerine _ kullanın)</p>
                    @error('code')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-6">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" checked class="mr-2">
                        <span class="text-gray-700">Aktif</span>
                    </label>
                </div>

                <div class="flex space-x-4">
                    <button type="submit" class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold">
                        Şirketi Oluştur
                    </button>
                    <a href="{{ route('admin.companies.index') }}" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 text-center px-6 py-3 rounded-lg font-semibold">
                        İptal
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
