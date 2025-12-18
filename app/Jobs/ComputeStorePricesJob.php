<?php

namespace App\Jobs;

use App\Pricing\StorePricingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ComputeStorePricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('compute-store-prices'))->dontRelease(),
        ];
    }

    public function handle(StorePricingService $service): void
    {
        try {
            $service->computeAndStore();
        } catch (\Throwable $e) {
            Log::error('ComputeStorePricesJob failed', ['message' => $e->getMessage()]);
            throw $e;
        }
    }
}

