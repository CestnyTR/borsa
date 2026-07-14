<?php

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\GoldSettingController;
use App\Http\Controllers\Admin\MultiplierPageController;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
    Route::post('/dashboard/fetch-now', [DashboardController::class, 'fetchNow'])->middleware('can:admin')->name('dashboard.fetchNow');
    Route::get('/pano/{store:slug}', [DashboardController::class, 'board'])->name('board');

    // SaaS abonelik / faturalandırma
    Route::get('/billing', [BillingController::class, 'pricing'])->name('billing.pricing');
    Route::get('/billing/status', [BillingController::class, 'status'])->name('billing.status');
    Route::post('/billing/subscribe/{plan}', [BillingController::class, 'subscribe'])->name('billing.subscribe');
    Route::match(['get', 'post'], '/billing/callback/{plan}', [BillingController::class, 'callback'])->name('billing.callback');
    Route::post('/billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
});

Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

// Admin Routes - Company Management
Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:admin'])->group(function () {
    Route::resource('companies', CompanyController::class);

    Route::post('settings', [GoldSettingController::class, 'store'])->name('settings.store');
    Route::put('settings/{setting}', [GoldSettingController::class, 'update'])->name('settings.update');
    Route::delete('settings/{setting}', [GoldSettingController::class, 'destroy'])->name('settings.destroy');

    Route::resource('stores', StoreController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::get('stores/{store}/multipliers', [MultiplierPageController::class, 'edit'])->name('stores.multipliers.edit');
    Route::post('stores/{store}/multipliers', [MultiplierPageController::class, 'update'])->name('stores.multipliers.update');
});
