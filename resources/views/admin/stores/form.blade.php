@extends('layouts.app')

@section('title', $store->exists ? 'Mağaza Düzenle' : 'Mağaza Oluştur')

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800">
            {{ $store->exists ? 'Mağaza Düzenle' : 'Mağaza Oluştur' }}
        </h1>
        <a href="{{ route('admin.stores.index') }}" class="text-sm text-gray-600 hover:text-gray-900 underline">Listeye dön</a>
    </div>

    <div class="mt-4 bg-white rounded-lg shadow-md p-6">
        <form method="POST" action="{{ $store->exists ? route('admin.stores.update', $store) : route('admin.stores.store') }}" class="space-y-4">
            @csrf
            @if($store->exists)
                @method('PUT')
            @endif

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Mağaza Adı</label>
                <input
                    type="text"
                    name="name"
                    value="{{ old('name', $store->name) }}"
                    required
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" class="h-4 w-4" {{ old('is_active', $store->is_active ?? true) ? 'checked' : '' }}>
                <label class="text-sm text-gray-700">Aktif</label>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                    Kaydet
                </button>

                @if($store->exists)
                    <a href="{{ route('admin.stores.multipliers.edit', $store) }}" class="text-sm text-blue-700 hover:text-blue-900 underline">
                        Çarpanları düzenle
                    </a>
                @endif
            </div>
        </form>
    </div>
@endsection

