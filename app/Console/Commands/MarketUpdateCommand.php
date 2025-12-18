<?php

namespace App\Console\Commands;

use App\Jobs\ComputeStorePricesJob;
use App\Jobs\FetchMarketQuotesJob;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

class MarketUpdateCommand extends Command
{
    protected $signature = 'market:update {--force : 30sn throttle devre dışı}';

    protected $description = 'Fetch market quotes and compute store prices (throttled ~30s).';

    public function handle(): int
    {
        if (!$this->option('force') && !$this->shouldRun()) {
            return self::SUCCESS;
        }

        Bus::chain([
            new FetchMarketQuotesJob(),
            new ComputeStorePricesJob(),
        ])->dispatch();

        return self::SUCCESS;
    }

    private function shouldRun(): bool
    {
        $now = CarbonImmutable::now()->timestamp;
        $key = 'market:update:last_run';

        $last = Cache::get($key);
        if (is_int($last) && ($now - $last) < 30) {
            return false;
        }

        Cache::put($key, $now, 120);
        return true;
    }
}

