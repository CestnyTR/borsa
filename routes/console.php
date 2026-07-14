<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Otomatik fiyat güncelleme - Her 15 dakikada bir
// Chrome Headless kullanarak Nadirdoviz'den gerçek fiyat çeker
Schedule::command('gold:fetch')->everyFifteenMinutes();

// Market quotes + store price calculation (throttled ~30s)
Schedule::command('market:update')->everyMinute();

// Sistem sağlığı kontrolü + uyarı (canlı akış / veri tazeliği)
Schedule::command('monitor:health')->everyFiveMinutes();
