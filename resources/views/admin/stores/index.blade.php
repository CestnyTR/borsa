@extends('layouts.app')

@section('title', 'Mağazalar')

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800">Mağazalar</h1>
        <a href="{{ route('admin.stores.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
            + Yeni Mağaza
        </a>
    </div>

    <div class="mt-4 bg-white rounded-lg shadow-md overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
            <tr class="text-left text-gray-600">
                <th class="py-3 px-4">Ad</th>
                <th class="py-3 px-4">Durum</th>
                <th class="py-3 px-4 text-right">Aksiyon</th>
            </tr>
            </thead>
            <tbody>
            @forelse($stores as $store)
                <tr class="border-t">
                    <td class="py-3 px-4 font-semibold">{{ $store->name }}</td>
                    <td class="py-3 px-4">
                        <span class="px-2 py-1 text-xs rounded {{ $store->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ $store->is_active ? 'Aktif' : 'Pasif' }}
                        </span>
                    </td>
                    <td class="py-3 px-4 text-right space-x-3">
                        <a class="text-blue-700 hover:text-blue-900 underline" href="{{ route('admin.stores.multipliers.edit', $store) }}">
                            Çarpanlar
                        </a>
                        <a class="text-gray-700 hover:text-gray-900 underline" href="{{ route('admin.stores.edit', $store) }}">
                            Düzenle
                        </a>
                    </td>
                </tr>
            @empty
                <tr class="border-t">
                    <td class="py-6 px-4 text-gray-500" colspan="3">Henüz mağaza yok.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection

