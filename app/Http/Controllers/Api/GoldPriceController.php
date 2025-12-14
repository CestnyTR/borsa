<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\GoldAsset;
use App\Models\GoldPrice;
use App\Services\CompanyGoldPriceService;
use App\Services\GoldPriceAggregator;
use App\Services\GoldScrapers\AhlatciDovizWebScraper;
use App\Services\GoldScrapers\NadirDovizOnsScraper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoldPriceController extends Controller
{
    public function __construct(
        private CompanyGoldPriceService $companyPriceService,
        private GoldPriceAggregator $priceAggregator
    ) {}

    /**
     * Get current gold price
     */
    public function current(): JsonResponse
    {
        $asset = GoldAsset::where('code', 'GRAM_GOLD_TRY')->first();

        if (!$asset) {
            return response()->json([
                'success' => false,
                'message' => 'Gold asset not found',
            ], 404);
        }

        $goldPrice = GoldPrice::where('asset_id', $asset->id)
            ->whereNull('valid_to')
            ->latest('valid_from')
            ->first();

        if (!$goldPrice) {
            return response()->json([
                'success' => false,
                'message' => 'No active gold price found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'asset' => [
                    'code' => $asset->code,
                    'name' => $asset->name,
                    'unit' => $asset->unit,
                ],
                'price' => [
                    'buy' => $goldPrice->buy_price ? (float) $goldPrice->buy_price : null,
                    'sell' => (float) $goldPrice->sell_price,
                ],
                'calculated_from' => $goldPrice->calculated_from,
                'valid_from' => $goldPrice->valid_from->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get all companies with their gold prices
     */
    public function allCompanies(): JsonResponse
    {
        try {
            $data = $this->companyPriceService->getAllCompaniesPrices();

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get specific company's gold prices
     */
    public function companyPrices(string $companyCode): JsonResponse
    {
        try {
            $company = Company::where('code', $companyCode)->first();

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company not found',
                ], 404);
            }

            $data = $this->companyPriceService->getCompanyPrices($company->id);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of all companies
     */
    public function companies(): JsonResponse
    {
        $companies = Company::where('is_active', true)
            ->select('id', 'name', 'code')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $companies,
        ]);
    }

    /**
     * Get current ons gold price (all sources)
     */
    public function ons(): JsonResponse
    {
        try {
            // 3 kaynaktan veri çek
            $sources = [];

            // 1. Ahlatcı Döviz
            try {
                $ahlatciScraper = new AhlatciDovizWebScraper();
                $ahlatciData = $ahlatciScraper->fetchPrice();
                $sources[] = [
                    'name' => 'Ahlatcı Döviz',
                    'code' => 'AHLATCI_DOVIZ',
                    'price' => [
                        'buy' => $ahlatciData['buy'],
                        'sell' => $ahlatciData['sell'],
                    ],
                ];
            } catch (\Exception $e) {
                $sources[] = [
                    'name' => 'Ahlatcı Döviz',
                    'code' => 'AHLATCI_DOVIZ',
                    'error' => $e->getMessage(),
                ];
            }

            // 2. Nadirdöviz (Ons)
            try {
                $nadirOnsScraper = new NadirDovizOnsScraper();
                $nadirOnsData = $nadirOnsScraper->fetchPrice();
                $sources[] = [
                    'name' => 'Nadirdöviz (Ons)',
                    'code' => 'NADIR_DOVIZ_ONS',
                    'price' => [
                        'buy' => $nadirOnsData['buy'],
                        'sell' => $nadirOnsData['sell'],
                    ],
                ];
            } catch (\Exception $e) {
                $sources[] = [
                    'name' => 'Nadirdöviz (Ons)',
                    'code' => 'NADIR_DOVIZ_ONS',
                    'error' => $e->getMessage(),
                ];
            }

            // 3. Nadirdöviz (Gram) - ons'a çevir
            try {
                $nadirGramScraper = new \App\Services\GoldScrapers\NadirDovizScraper();
                $nadirGramData = $nadirGramScraper->fetchPrice();
                // Gram'ı ons'a çevir (1 ons = 31.1 gram)
                $sources[] = [
                    'name' => 'Nadirdöviz (Gram→Ons)',
                    'code' => 'NADIR_DOVIZ_GRAM',
                    'price' => [
                        'buy' => $nadirGramData['buy'] ? $nadirGramData['buy'] * 31.1 : null,
                        'sell' => $nadirGramData['sell'] * 31.1,
                    ],
                ];
            } catch (\Exception $e) {
                $sources[] = [
                    'name' => 'Nadirdöviz (Gram→Ons)',
                    'code' => 'NADIR_DOVIZ_GRAM',
                    'error' => $e->getMessage(),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'sources' => $sources,
                    'fetched_at' => now()->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ons altın fiyatları alınırken hata oluştu: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Trigger gold price fetching from all sources
     */
    public function fetchNow(): JsonResponse
    {
        try {
            $rawPrices = $this->priceAggregator->fetchFromAllSources();

            if (empty($rawPrices)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No prices were fetched from any source.',
                ], 500);
            }

            $goldPrice = $this->priceAggregator->aggregateAndSave($rawPrices);

            return response()->json([
                'success' => true,
                'message' => 'Fiyatlar başarıyla güncellendi!',
                'data' => [
                    'sources_count' => count($rawPrices),
                    'raw_prices' => $rawPrices,
                    'aggregated_price' => (float) $goldPrice->sell_price,
                    'fetched_at' => $goldPrice->valid_from->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fiyatlar güncellenirken hata oluştu: ' . $e->getMessage(),
            ], 500);
        }
    }
}
