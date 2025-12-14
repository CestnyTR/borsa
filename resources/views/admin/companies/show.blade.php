<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $company->name }} - Yönetim</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100">
    <div x-data="{ showAddForm: false, editingSetting: null }" class="min-h-screen">
        <nav class="bg-white shadow-md">
            <div class="container mx-auto px-4 py-4">
                <a href="{{ route('admin.companies.index') }}" class="text-blue-600 hover:text-blue-800">← Şirketler Listesi</a>
            </div>
        </nav>

        <div class="container mx-auto px-4 py-8">
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Company Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold">{{ $company->name }}</h1>
                        <p class="text-gray-500">Kod: {{ $company->code }}</p>
                        <span class="inline-block mt-2 px-3 py-1 text-sm rounded {{ $company->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ $company->is_active ? 'Aktif' : 'Pasif' }}
                        </span>
                    </div>
                    <div class="flex space-x-2">
                        <a href="{{ route('admin.companies.edit', $company) }}" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded">
                            Düzenle
                        </a>
                        <form action="{{ route('admin.companies.destroy', $company) }}" method="POST" onsubmit="return confirm('Şirketi silmek istediğinizden emin misiniz?');">
                            @csrf
                            @method('DELETE')
                            <button class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded">
                                Sil
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Settings Section -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Çarpanlar ({{ $company->goldSettings->count() }})</h2>
                    <button @click="showAddForm = !showAddForm" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">
                        <span x-show="!showAddForm">+ Yeni Çarpan Ekle</span>
                        <span x-show="showAddForm">✕ İptal</span>
                    </button>
                </div>

                <!-- Add New Setting Form -->
                <div x-show="showAddForm" class="bg-gray-50 p-6 rounded-lg mb-6">
                    <h3 class="font-bold mb-4">Yeni Çarpan Ekle</h3>
                    <form action="{{ route('admin.settings.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @csrf
                        <input type="hidden" name="company_id" value="{{ $company->id }}">
                        <input type="hidden" name="asset_id" value="{{ $assets->first()->id }}">

                        <div>
                            <label class="block text-sm font-semibold mb-1">Gram Ağırlığı *</label>
                            <input type="number" step="0.001" name="weight_gram" required
                                   class="w-full border rounded px-3 py-2"
                                   placeholder="ör: 0.5">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-1">Yüzde Kar (%)</label>
                            <input type="number" step="0.01" name="markup_percent"
                                   class="w-full border rounded px-3 py-2"
                                   placeholder="ör: 2.5">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-1">Sabit TL Kar</label>
                            <input type="number" step="0.01" name="markup_fixed"
                                   class="w-full border rounded px-3 py-2"
                                   placeholder="ör: 5.00">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-1">Yuvarlama Adımı</label>
                            <input type="number" step="0.01" name="rounding_step" value="0.10"
                                   class="w-full border rounded px-3 py-2"
                                   placeholder="ör: 0.10">
                        </div>

                        <div class="col-span-full flex items-center">
                            <input type="checkbox" name="is_active" checked class="mr-2">
                            <label class="text-sm">Aktif</label>
                        </div>

                        <div class="col-span-full">
                            <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded">
                                Ekle
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Settings Table -->
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-4 py-2 text-left">Gram</th>
                                <th class="px-4 py-2 text-left">% Kar</th>
                                <th class="px-4 py-2 text-left">Sabit Kar (TL)</th>
                                <th class="px-4 py-2 text-left">Yuvarlama</th>
                                <th class="px-4 py-2 text-left">Durum</th>
                                <th class="px-4 py-2 text-left">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($company->goldSettings as $setting)
                                <tr class="border-b hover:bg-gray-50"
                                    x-data="{ editing: false, formData: { weight_gram: {{ $setting->weight_gram }}, markup_percent: {{ $setting->markup_percent }}, markup_fixed: {{ $setting->markup_fixed }}, rounding_step: {{ $setting->rounding_step }}, is_active: {{ $setting->is_active ? 1 : 0 }} } }">

                                    <!-- View Mode -->
                                    <template x-if="!editing">
                                        <td colspan="6" class="px-4 py-3">
                                            <div class="grid grid-cols-6 gap-4">
                                                <div class="font-semibold">{{ $setting->weight_gram }}g</div>
                                                <div>{{ $setting->markup_percent }}%</div>
                                                <div>{{ $setting->markup_fixed }} TL</div>
                                                <div>{{ $setting->rounding_step }}</div>
                                                <div>
                                                    <span class="px-2 py-1 text-xs rounded {{ $setting->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                                        {{ $setting->is_active ? 'Aktif' : 'Pasif' }}
                                                    </span>
                                                </div>
                                                <div class="flex space-x-2">
                                                    <button @click="editing = true" class="text-blue-600 hover:text-blue-800">Düzenle</button>
                                                    <form action="{{ route('admin.settings.destroy', $setting) }}" method="POST" class="inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-600 hover:text-red-800"
                                                                onclick="return confirm('Silmek istediğinizden emin misiniz?');">Sil</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </td>
                                    </template>

                                    <!-- Edit Mode -->
                                    <template x-if="editing">
                                        <td colspan="6" class="px-4 py-3 bg-blue-50">
                                            <form action="{{ route('admin.settings.update', $setting) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <div class="grid grid-cols-6 gap-2 items-center">
                                                    <input type="number" step="0.001" x-model="formData.weight_gram" name="weight_gram" required
                                                           class="border rounded px-2 py-1 text-sm">
                                                    <input type="number" step="0.01" x-model="formData.markup_percent" name="markup_percent"
                                                           class="border rounded px-2 py-1 text-sm">
                                                    <input type="number" step="0.01" x-model="formData.markup_fixed" name="markup_fixed"
                                                           class="border rounded px-2 py-1 text-sm">
                                                    <input type="number" step="0.01" x-model="formData.rounding_step" name="rounding_step"
                                                           class="border rounded px-2 py-1 text-sm">
                                                    <label class="flex items-center text-sm">
                                                        <input type="checkbox" x-model="formData.is_active" name="is_active" class="mr-1">
                                                        Aktif
                                                    </label>
                                                    <div class="flex space-x-1">
                                                        <button type="submit" class="bg-green-500 text-white px-3 py-1 rounded text-sm">Kaydet</button>
                                                        <button type="button" @click="editing = false" class="bg-gray-300 text-gray-700 px-3 py-1 rounded text-sm">İptal</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </td>
                                    </template>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                        Henüz çarpan eklenmemiş. Yukarıdaki butonu kullanarak ekleyebilirsiniz.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
