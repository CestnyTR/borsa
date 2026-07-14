<?php

use App\Http\Controllers\Api\GoldPriceController;
use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\V1\Admin\MultiplierController;
use App\Http\Controllers\Api\V1\MarketController;
use App\Http\Controllers\Api\V1\StorePriceController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/token', [AuthTokenController::class, 'issue'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    // Current gold price (gram)
    Route::get('/gold/current', [GoldPriceController::class, 'current']);

    // Current ons gold price
    Route::get('/gold/ons', [GoldPriceController::class, 'ons']);

    // Fetch gold prices now (trigger scraping)
    Route::post('/gold/fetch-now', [GoldPriceController::class, 'fetchNow']);

    // Companies
    Route::get('/companies', [GoldPriceController::class, 'companies']);

    // All companies with prices
    Route::get('/companies/prices', [GoldPriceController::class, 'allCompanies']);

    // Specific company prices
    Route::get('/companies/{code}/prices', [GoldPriceController::class, 'companyPrices']);

    Route::prefix('v1')->group(function () {
        Route::get('/market/latest', [MarketController::class, 'latest']);
        Route::get('/stores/{store}/prices/latest', [StorePriceController::class, 'latest']);
    });
});

Route::prefix('v1/admin')
    ->middleware(['auth:sanctum', 'can:admin'])
    ->group(function () {
        Route::post('/stores/{store}/multipliers', [MultiplierController::class, 'upsert']);
    });
