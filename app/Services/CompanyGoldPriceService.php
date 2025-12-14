<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyGoldSetting;
use App\Models\GoldAsset;
use App\Models\GoldPrice;

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

        $asset = GoldAsset::where('code', 'GRAM_GOLD_TRY')->first();

        if (!$asset) {
            throw new \Exception('Gold asset not found');
        }

        // Get latest gold price
        $goldPrice = GoldPrice::where('asset_id', $asset->id)
            ->whereNull('valid_to')
            ->latest('valid_from')
            ->first();

        if (!$goldPrice) {
            throw new \Exception('No active gold price found');
        }

        // Get company settings
        $settings = CompanyGoldSetting::where('company_id', $companyId)
            ->where('asset_id', $asset->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $result = [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'code' => $company->code,
            ],
            'base_price' => [
                'asset' => $asset->name,
                'unit' => $asset->unit,
                'sell_price' => (float) $goldPrice->sell_price,
                'valid_from' => $goldPrice->valid_from->toIso8601String(),
            ],
            'prices' => [],
        ];

        foreach ($settings as $setting) {
            $calculated = $this->calculatePrice(
                (float) $goldPrice->sell_price,
                $setting
            );

            $result['prices'][] = $calculated;
        }

        return $result;
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
