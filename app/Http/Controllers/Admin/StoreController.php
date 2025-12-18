<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function index()
    {
        $stores = Store::query()->orderBy('name')->get();
        return view('admin.stores.index', compact('stores'));
    }

    public function create()
    {
        return view('admin.stores.form', [
            'store' => new Store(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        $store = Store::query()->create($data);

        return redirect()
            ->route('admin.stores.edit', $store)
            ->with('success', 'Mağaza oluşturuldu.');
    }

    public function edit(Store $store)
    {
        return view('admin.stores.form', compact('store'));
    }

    public function update(Request $request, Store $store)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        $store->update($data);

        return redirect()
            ->route('admin.stores.edit', $store)
            ->with('success', 'Mağaza güncellendi.');
    }
}

