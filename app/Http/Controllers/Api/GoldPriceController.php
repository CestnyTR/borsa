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
use App\Services\GoldScrapers\HaremAltinScraper;
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
            return $this->apiError('Gold asset not found', [], 404);
        }

        $goldPrice = GoldPrice::where('asset_id', $asset->id)
            ->whereNull('valid_to')
            ->latest('valid_from')
            ->first();

        if (!$goldPrice) {
            return $this->apiError('No active gold price found', [], 404);
        }

        return $this->apiSuccess([
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
        ], [
            'last_update_at' => $asset->last_price_at?->toIso8601String() ?? $goldPrice->valid_from->toIso8601String(),
        ]);
    }

    /**
     * Get all companies with their gold prices
     */
    public function allCompanies(): JsonResponse
    {
        $user = request()->user();
        if (!$user || !$user->isAdmin()) {
            return $this->apiError('Yetkisiz erisim.', [], 403);
        }

        try {
            $data = $this->companyPriceService->getAllCompaniesPrices();

            $lastUpdateAt = Company::query()->where('is_active', true)->max('last_gold_prices_at');
            return $this->apiSuccess($data, [
                'last_update_at' => $lastUpdateAt ? (string) $lastUpdateAt : null,
            ]);
        } catch (\Exception $e) {
            return $this->apiError('Icerik hazirlanamadi.', [], 500);
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
                return $this->apiError('Company not found', [], 404);
            }

            $user = request()->user();
            if ($user && !$user->isAdmin() && (int) $user->company_id !== (int) $company->id) {
                return $this->apiError('Yetkisiz erisim.', [], 403);
            }
            if ($user && !$user->isAdmin() && !$user->company_id) {
                return $this->apiError('Sirket atamasi bulunamadi.', [], 403);
            }

            $data = $this->companyPriceService->getCompanyPrices($company->id);

            return $this->apiSuccess($data, [
                'last_update_at' => $company->last_gold_prices_at?->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            return $this->apiError('Icerik hazirlanamadi.', [], 500);
        }
    }

    /**
     * Get list of all companies
     */
    public function companies(): JsonResponse
    {
        $user = request()->user();
        if ($user && !$user->isAdmin()) {
            if (!$user->company_id) {
                return $this->apiError('Sirket atamasi bulunamadi.', [], 403);
            }
            $companies = Company::where('is_active', true)
                ->where('id', $user->company_id)
                ->select('id', 'name', 'code')
                ->get();
        } else {
            $companies = Company::where('is_active', true)
                ->select('id', 'name', 'code')
                ->get();
        }

        return $this->apiSuccess($companies->toArray());
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
                    'currency' => 'TRY',
                    'unit' => 'Gram',
                    'price' => [
                        'buy' => $ahlatciData['buy'],
                        'sell' => $ahlatciData['sell'],
                    ],
                ];
            } catch (\Exception $e) {
                $sources[] = [
                    'name' => 'Ahlatcı Döviz',
                    'code' => 'AHLATCI_DOVIZ',
                    'currency' => 'TRY',
                    'unit' => 'Gram',
                    'error' => 'unavailable',
                ];
            }

            // 2. Nadirdöviz (Ons)
            try {
                $nadirOnsScraper = new NadirDovizOnsScraper();
                $nadirOnsData = $nadirOnsScraper->fetchPrice();
                $sources[] = [
                    'name' => 'Nadirdöviz (Ons)',
                    'code' => 'NADIR_DOVIZ_ONS',
                    'currency' => 'USD',
                    'unit' => 'Ons',
                    'price' => [
                        'buy' => $nadirOnsData['buy'],
                        'sell' => $nadirOnsData['sell'],
                    ],
                ];
            } catch (\Exception $e) {
                $sources[] = [
                    'name' => 'Nadirdöviz (Ons)',
                    'code' => 'NADIR_DOVIZ_ONS',
                    'currency' => 'USD',
                    'unit' => 'Ons',
                    'error' => 'unavailable',
                ];
            }

            // 3. Harem Altın
            try {
                $haremAltinScraper = new HaremAltinScraper();
                $haremAltinData = $haremAltinScraper->fetchPrice();
                $sources[] = [
                    'name' => 'Harem Altın',
                    'code' => 'HAREM_ALTIN',
                    'currency' => 'USD',
                    'unit' => 'Ons',
                    'price' => [
                        'buy' => $haremAltinData['buy'],
                        'sell' => $haremAltinData['sell'],
                    ],
                ];
            } catch (\Exception $e) {
                $sources[] = [
                    'name' => 'Harem Altın',
                    'code' => 'HAREM_ALTIN',
                    'currency' => 'USD',
                    'unit' => 'Ons',
                    'error' => 'unavailable',
                ];
            }

            return $this->apiSuccess([
                'sources' => $sources,
                'fetched_at' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            return $this->apiError('Ons altin fiyatlari alinirken hata olustu.', [], 500);
        }
    }

    /**
     * Trigger gold price fetching from all sources
     */
    public function fetchNow(): JsonResponse
    {
        $user = request()->user();
        if (!$user || !$user->isAdmin()) {
            return $this->apiError('Yetkisiz erisim.', [], 403);
        }

        try {
            $rawPrices = $this->priceAggregator->fetchFromAllSources();

            if (empty($rawPrices)) {
                return $this->apiError('Kaynaklardan fiyat alinamadi.', [], 500);
            }

            $goldPrice = $this->priceAggregator->aggregateAndSave($rawPrices);

            return $this->apiSuccess([
                'sources_count' => count($rawPrices),
                'raw_prices' => $rawPrices,
                'aggregated_price' => (float) $goldPrice->sell_price,
                'fetched_at' => $goldPrice->valid_from->toIso8601String(),
            ], [
                'last_update_at' => $goldPrice->valid_from->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            return $this->apiError('Fiyatlar guncellenirken hata olustu.', [], 500);
        }
    }
}
