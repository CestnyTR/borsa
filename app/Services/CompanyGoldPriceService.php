<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyGoldSetting;
use App\Models\GoldAsset;
use App\Models\GoldPrice;
use App\Models\GoldRawPrice;
use Illuminate\Support\Facades\DB;

class CompanyGoldPriceService
{
    /**
     * Get calculated prices for a specific company
     *
     * @param int $companyId
     * @return array
     */
    public function getCompanyPrices(int $companyId): array
    {
        $company = Company::with('goldSettings.asset')->findOrFail($companyId);

        [$asset, $goldPrice] = $this->getAssetAndOfficialPrice();

        // Get company settings
        $settings = CompanyGoldSetting::where('company_id', $companyId)
            ->where('asset_id', $asset->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $officialBase = (float) $goldPrice->sell_price;
        $officialPrices = [];
        foreach ($settings as $setting) {
            $officialPrices[] = $this->calculatePrice($officialBase, $setting);
        }

        $priceSets = [];

        foreach ($this->getLatestRawSourcesForAsset($asset->id) as $source) {
            $baseSell = (float) $source['sell_price'];
            $sourcePrices = [];
            foreach ($settings as $setting) {
                $sourcePrices[] = $this->calculatePrice($baseSell, $setting);
            }

            $priceSets[] = [
                'code' => $source['code'],
                'name' => $source['name'],
                'currency' => 'TRY',
                'unit' => $asset->unit,
                'base_price' => [
                    'asset' => $asset->name,
                    'unit' => $asset->unit,
                    'sell_price' => $baseSell,
                    'fetched_at' => $source['fetched_at'],
                ],
                'prices' => $sourcePrices,
            ];
        }

        $priceSets[] = [
            'code' => 'OFFICIAL',
            'name' => 'Ortalama (Resmi)',
            'currency' => 'TRY',
            'unit' => $asset->unit,
            'base_price' => [
                'asset' => $asset->name,
                'unit' => $asset->unit,
                'sell_price' => $officialBase,
                'valid_from' => $goldPrice->valid_from->toIso8601String(),
            ],
            'prices' => $officialPrices,
        ];

        $result = [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'code' => $company->code,
            ],
            'base_price' => [
                'asset' => $asset->name,
                'unit' => $asset->unit,
                'sell_price' => $officialBase,
                'valid_from' => $goldPrice->valid_from->toIso8601String(),
            ],
            'prices' => $officialPrices,
            'price_sets' => $priceSets,
        ];

        return $result;
    }

    /**
     * @return array{company_id: int, items: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function buildCompanyBroadcastPayload(int $companyId, \DateTimeInterface $emittedAt, string $traceId): array
    {
        $company = Company::with('goldSettings')->findOrFail($companyId);
        [$asset, $goldPrice] = $this->getAssetAndOfficialPrice();

        $settings = CompanyGoldSetting::where('company_id', $companyId)
            ->where('asset_id', $asset->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $settingsHash = hash('sha256', $settings->map(fn ($s) => [
            'id' => $s->id,
            'weight_gram' => (float) $s->weight_gram,
            'markup_percent' => (float) $s->markup_percent,
            'markup_fixed' => (float) $s->markup_fixed,
            'rounding_step' => (float) $s->rounding_step,
            'is_active' => (bool) $s->is_active,
            'sort_order' => (int) $s->sort_order,
        ])->values()->all());

        $officialBase = (float) $goldPrice->sell_price;
        $items = [];
        foreach ($settings as $setting) {
            $calc = $this->calculatePrice($officialBase, $setting);
            $items[] = [
                'asset_id' => $asset->id,
                'final_price' => $calc['final_price'],
                'settings_hash' => $settingsHash,
                'updated_at' => $goldPrice->valid_from->toIso8601String(),
            ];
        }

        return [
            'company_id' => $company->id,
            'items' => $items,
            'meta' => [
                'emitted_at' => $emittedAt->format(DATE_ATOM),
                'trace_id' => $traceId,
                'last_update_at' => $company->last_gold_prices_at?->toIso8601String() ?? $goldPrice->valid_from->toIso8601String(),
            ],
        ];
    }

    /**
     * Get prices for all active companies
     *
     * @return array
     */
    public function getAllCompaniesPrices(): array
    {
        $companies = Company::where('is_active', true)->get();

        $result = [];

        foreach ($companies as $company) {
            try {
                $result[] = $this->getCompanyPrices($company->id);
            } catch (\Exception $e) {
                // Skip companies with errors
                continue;
            }
        }

        return $result;
    }

    /**
     * @return array{0: GoldAsset, 1: GoldPrice}
     */
    private function getAssetAndOfficialPrice(): array
    {
        $asset = GoldAsset::where('code', 'GRAM_GOLD_TRY')->first();

        if (!$asset) {
            throw new \Exception('Gold asset not found');
        }

        $goldPrice = GoldPrice::where('asset_id', $asset->id)
            ->whereNull('valid_to')
            ->latest('valid_from')
            ->first();

        if (!$goldPrice) {
            throw new \Exception('No active gold price found');
        }

        return [$asset, $goldPrice];
    }

    /**
     * @return array<int, array{code: string, name: string, sell_price: float, fetched_at: string}>
     */
    private function getLatestRawSourcesForAsset(int $assetId): array
    {
        $latestIds = GoldRawPrice::query()
            ->select('source_id', DB::raw('MAX(id) as max_id'))
            ->where('asset_id', $assetId)
            ->groupBy('source_id');

        $rows = DB::table('gold_raw_prices as grp')
            ->joinSub($latestIds, 'latest', function ($join) {
                $join->on('grp.id', '=', 'latest.max_id');
            })
            ->join('gold_sources as s', 's.id', '=', 'grp.source_id')
            ->where('s.is_active', 1)
            ->select(['s.code', 's.name', 'grp.sell_price', 'grp.fetched_at'])
            ->orderBy('s.code')
            ->get();

        return $rows->map(fn ($row) => [
            'code' => (string) $row->code,
            'name' => (string) $row->name,
            'sell_price' => (float) $row->sell_price,
            'fetched_at' => (string) $row->fetched_at,
        ])->all();
    }

    /**
     * Calculate final price for a specific setting
     *
     * @param float $basePrice
     * @param CompanyGoldSetting $setting
     * @return array
     */
    private function calculatePrice(float $basePrice, CompanyGoldSetting $setting): array
    {
        // Base calculation: price per gram * weight
        $base = $basePrice * (float) $setting->weight_gram;

        // Apply percentage markup
        if ($setting->markup_percent > 0) {
            $base += $base * ((float) $setting->markup_percent / 100);
        }

        // Apply fixed markup
        if ($setting->markup_fixed > 0) {
            $base += (float) $setting->markup_fixed;
        }

        // Apply rounding
        $finalPrice = $this->roundToStep($base, (float) $setting->rounding_step);

        return [
            'weight_gram' => (float) $setting->weight_gram,
            'raw_price' => round($base, 2),
            'final_price' => $finalPrice,
            'markup' => [
                'percent' => (float) $setting->markup_percent,
                'fixed' => (float) $setting->markup_fixed,
                'rounding_step' => (float) $setting->rounding_step,
            ],
        ];
    }

    /**
     * Round value to nearest step
     *
     * @param float $value
     * @param float $step
     * @return float
     */
    private function roundToStep(float $value, float $step): float
    {
        if ($step <= 0) {
            return round($value, 2);
        }

        return round($value / $step) * $step;
    }

    /**
     * Get price for specific weight and company
     *
     * @param int $companyId
     * @param float $weightGram
     * @return array|null
     */
    public function getCompanyPriceByWeight(int $companyId, float $weightGram): ?array
    {
        $prices = $this->getCompanyPrices($companyId);

        foreach ($prices['prices'] as $price) {
            if ($price['weight_gram'] == $weightGram) {
                return array_merge(['company' => $prices['company']], $price);
            }
        }

        return null;
    }
}
