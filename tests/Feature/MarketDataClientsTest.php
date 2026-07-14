<?php

namespace Tests\Feature;

use App\MarketData\Clients\NadirClient;
use App\MarketData\Support\HaremSocketReader;
use App\MarketData\Support\NumberParser;
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

    public function test_harem_socket_reader_extracts_gram_and_ons_rows(): void
    {
        // HAREM artık Socket.IO ile yayınlıyor; price_changed payload'ında
        // gram altın ALTIN key'inde, ons ONS key'inde gelir. Parsing testi:
        $data = [
            'USDTRY' => ['code' => 'USDTRY', 'alis' => '40.00', 'satis' => '40.10'],
            'ALTIN' => ['code' => 'ALTIN', 'alis' => '6077.800', 'satis' => '6105.670'],
            'ONS' => ['code' => 'ONS', 'alis' => '4024.40', 'satis' => '4024.90'],
        ];

        $has = HaremSocketReader::extractRow($data, ['ALTIN', 'HASALTIN', 'HAS'], ['ALTIN', 'HAS']);
        $ons = HaremSocketReader::extractRow($data, ['ONS', 'XAUUSD'], ['ONS', 'XAUUSD']);

        $this->assertNotNull($has);
        $this->assertSame(6077.8, NumberParser::parseLocaleNumber($has['alis']));
        $this->assertSame(6105.67, NumberParser::parseLocaleNumber($has['satis']));

        $this->assertNotNull($ons);
        $this->assertSame(4024.4, NumberParser::parseLocaleNumber($ons['alis']));
        $this->assertSame(4024.9, NumberParser::parseLocaleNumber($ons['satis']));
    }
}

