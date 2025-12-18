<?php

namespace Tests\Feature;

use App\MarketData\Clients\HaremClient;
use App\MarketData\Clients\NadirClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketDataClientsTest extends TestCase
{
    public function test_nadir_client_returns_has_and_ons_quotes(): void
    {
        Http::fake([
            'https://www.nadirdoviz.com/api/KurOku' => Http::response([
                'list' => [
                    ['parite' => 'AU/TL', 'baslik' => 'Altın/TL', 'alis' => 3000.5, 'satis' => 3001.0],
                    ['parite' => 'XAU/USD', 'baslik' => 'Altın/ONS', 'alis' => 2050.25, 'satis' => 2051.75],
                ],
            ], 200),
        ]);

        $quotes = (new NadirClient())->fetchQuotes();

        $this->assertCount(2, $quotes);
        $this->assertSame('HAS', $quotes[0]->instrument);
        $this->assertSame('TRY', $quotes[0]->currency);
        $this->assertSame(3000.5, $quotes[0]->bid);
        $this->assertSame('ONS', $quotes[1]->instrument);
        $this->assertSame('USD', $quotes[1]->currency);
        $this->assertSame(2051.75, $quotes[1]->ask);
    }

    public function test_harem_client_returns_has_and_ons_quotes(): void
    {
        Http::fake([
            'https://canlipiyasalar.haremaltin.com/tmp/altin.json*' => Http::response([
                'data' => [
                    'HASALTIN' => ['alis' => '3.000,50', 'satis' => '3.010,75'],
                    'ONS' => ['alis' => '2.050,25', 'satis' => '2.051,75'],
                ],
            ], 200),
        ]);

        $quotes = (new HaremClient())->fetchQuotes();

        $this->assertCount(2, $quotes);
        $this->assertSame('HAS', $quotes[0]->instrument);
        $this->assertSame(3000.5, $quotes[0]->bid);
        $this->assertSame('ONS', $quotes[1]->instrument);
        $this->assertSame(2051.75, $quotes[1]->ask);
    }
}

