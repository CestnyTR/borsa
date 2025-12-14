<?php

use App\Http\Controllers\Api\GoldPriceController;
use Illuminate\Support\Facades\Route;

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
