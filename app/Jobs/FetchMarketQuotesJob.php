<?php

namespace App\Jobs;

use App\MarketData\MarketQuoteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchMarketQuotesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('fetch-market-quotes'))->dontRelease(),
        ];
    }

    public function handle(MarketQuoteService $service): void
    {
        try {
            $service->fetchAndStoreActiveSources();
        } catch (\Throwable $e) {
            Log::error('FetchMarketQuotesJob failed', ['message' => $e->getMessage()]);
            throw $e;
        }
    }
}

