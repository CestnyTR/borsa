<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
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
        $user = $request->user();

        // Plan limiti: sahip başına mağaza sayısı.
        $max = (int) $user->planFeature('max_stores', 1);
        if ($user->ownedStores()->count() >= $max) {
            return redirect()
                ->route('billing.pricing')
                ->with('error', "Planınız en fazla {$max} mağazaya izin veriyor. Daha fazlası için Pro'ya yükseltin.");
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['owner_id'] = $user->id;

        $store = Store::query()->create($data);
        AuditLog::record('store.created', $store, ['name' => $store->name]);

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
        AuditLog::record('store.updated', $store, ['name' => $store->name]);

        return redirect()
            ->route('admin.stores.edit', $store)
            ->with('success', 'Mağaza güncellendi.');
    }
}

