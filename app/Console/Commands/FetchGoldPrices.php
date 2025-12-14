<?php

namespace App\Console\Commands;

use App\Services\GoldPriceAggregator;
use Illuminate\Console\Command;

class FetchGoldPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gold:fetch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch gold prices from all sources and aggregate them';

    /**
     * Execute the console command.
     */
    public function handle(GoldPriceAggregator $aggregator): int
    {
        $this->info('Fetching gold prices from all sources...');

        try {
            $rawPrices = $aggregator->fetchFromAllSources();

            if (empty($rawPrices)) {
                $this->error('No prices were fetched from any source.');
                return self::FAILURE;
            }

            $this->info('Fetched prices from ' . count($rawPrices) . ' source(s):');

            foreach ($rawPrices as $source => $price) {
                $this->line("  - {$source}: {$price} TL");
            }

            $this->info('');
            $this->info('Aggregating prices...');

            $goldPrice = $aggregator->aggregateAndSave($rawPrices);

            $this->info('Final aggregated price: ' . $goldPrice->sell_price . ' TL');
            $this->info('');
            $this->info('Gold prices fetched and aggregated successfully!');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to fetch gold prices: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
