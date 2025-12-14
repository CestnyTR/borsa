<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\GoldAsset;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::withCount('goldSettings')->get();
        return view('admin.companies.index', compact('companies'));
    }

    public function create()
    {
        return view('admin.companies.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:companies,code',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $company = Company::create($validated);

        return redirect()->route('admin.companies.show', $company)
            ->with('success', 'Şirket başarıyla oluşturuldu!');
    }

    public function show(Company $company)
    {
        $company->load(['goldSettings' => function($query) {
            $query->with('asset')->orderBy('sort_order');
        }]);

        $assets = GoldAsset::where('is_active', true)->get();

        return view('admin.companies.show', compact('company', 'assets'));
    }

    public function edit(Company $company)
    {
        return view('admin.companies.edit', compact('company'));
    }

    public function update(Request $request, Company $company)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:companies,code,' . $company->id,
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $company->update($validated);

        return redirect()->route('admin.companies.show', $company)
            ->with('success', 'Şirket başarıyla güncellendi!');
    }

    public function destroy(Company $company)
    {
        $company->delete();

        return redirect()->route('admin.companies.index')
            ->with('success', 'Şirket başarıyla silindi!');
    }
}
