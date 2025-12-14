<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şirket Yönetimi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="bg-white shadow-md">
            <div class="container mx-auto px-4 py-4 flex justify-between items-center">
                <div class="flex space-x-4">
                    <a href="/" class="text-gray-600 hover:text-gray-900">Dashboard</a>
                    <a href="{{ route('admin.companies.index') }}" class="text-blue-600 font-semibold">Şirket Yönetimi</a>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-gray-600 hover:text-gray-900 underline">Çıkış</button>
                </form>
            </div>
        </nav>

        <!-- Content -->
        <div class="container mx-auto px-4 py-8">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold">Şirketler</h1>
                <a href="{{ route('admin.companies.create') }}" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg">
                    + Yeni Şirket Ekle
                </a>
            </div>

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($companies as $company)
                    <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-xl font-bold">{{ $company->name }}</h3>
                                <p class="text-gray-500 text-sm">{{ $company->code }}</p>
                            </div>
                            <span class="px-2 py-1 text-xs rounded {{ $company->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $company->is_active ? 'Aktif' : 'Pasif' }}
                            </span>
                        </div>

                        <div class="text-sm text-gray-600 mb-4">
                            <p>{{ $company->gold_settings_count }} çarpan tanımlı</p>
                        </div>

                        <div class="flex space-x-2">
                            <a href="{{ route('admin.companies.show', $company) }}" class="flex-1 bg-blue-500 hover:bg-blue-600 text-white text-center px-4 py-2 rounded">
                                Detay & Çarpanlar
                            </a>
                            <a href="{{ route('admin.companies.edit', $company) }}" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded">
                                Düzenle
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-center py-12">
                        <p class="text-gray-500 text-lg">Henüz şirket yok. Hemen bir tane ekleyin!</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</body>
</html>
