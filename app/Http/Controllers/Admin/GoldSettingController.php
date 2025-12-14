<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyGoldSetting;
use App\Models\GoldAsset;
use Illuminate\Http\Request;

class GoldSettingController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'asset_id' => 'required|exists:gold_assets,id',
            'weight_gram' => 'required|numeric|min:0',
            'markup_percent' => 'nullable|numeric|min:0|max:100',
            'markup_fixed' => 'nullable|numeric|min:0',
            'rounding_step' => 'nullable|numeric|min:0.01',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $validated['sort_order'] = CompanyGoldSetting::where('company_id', $validated['company_id'])->max('sort_order') + 1;

        CompanyGoldSetting::create($validated);

        return redirect()->route('admin.companies.show', $validated['company_id'])
            ->with('success', 'Çarpan başarıyla eklendi!');
    }

    public function update(Request $request, CompanyGoldSetting $setting)
    {
        $validated = $request->validate([
            'weight_gram' => 'required|numeric|min:0',
            'markup_percent' => 'nullable|numeric|min:0|max:100',
            'markup_fixed' => 'nullable|numeric|min:0',
            'rounding_step' => 'nullable|numeric|min:0.01',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $setting->update($validated);

        return redirect()->route('admin.companies.show', $setting->company_id)
            ->with('success', 'Çarpan başarıyla güncellendi!');
    }

    public function destroy(CompanyGoldSetting $setting)
    {
        $companyId = $setting->company_id;
        $setting->delete();

        return redirect()->route('admin.companies.show', $companyId)
            ->with('success', 'Çarpan başarıyla silindi!');
    }
}
