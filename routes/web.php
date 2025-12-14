<?php

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\GoldSettingController;
use App\Http\Controllers\Admin\ManualPriceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard');
});

Route::get('/dashboard', function () {
    return view('dashboard');
});

// Admin Routes - Company Management
Route::prefix('admin')->name('admin.')->group(function () {
    Route::resource('companies', CompanyController::class);

    Route::post('settings', [GoldSettingController::class, 'store'])->name('settings.store');
    Route::put('settings/{setting}', [GoldSettingController::class, 'update'])->name('settings.update');
    Route::delete('settings/{setting}', [GoldSettingController::class, 'destroy'])->name('settings.destroy');

    // Manuel fiyat girişi
    Route::get('prices/manual', [ManualPriceController::class, 'create'])->name('prices.create');
    Route::post('prices/manual', [ManualPriceController::class, 'store'])->name('prices.store');
});
