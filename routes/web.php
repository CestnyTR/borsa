<?php

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\GoldSettingController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard');
});

Route::get('/dashboard', function () {
    return view('dashboard');
});

Route::get('/company/{companyCode}', function (string $companyCode) {
    return view('dashboard', ['companyCode' => $companyCode]);
})->where('companyCode', '[A-Za-z0-9_-]+');

Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

// Admin Routes - Company Management
Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::resource('companies', CompanyController::class);

    Route::post('settings', [GoldSettingController::class, 'store'])->name('settings.store');
    Route::put('settings/{setting}', [GoldSettingController::class, 'update'])->name('settings.update');
    Route::delete('settings/{setting}', [GoldSettingController::class, 'destroy'])->name('settings.destroy');

});
