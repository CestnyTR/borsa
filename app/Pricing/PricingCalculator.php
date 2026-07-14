<?php

namespace App\Pricing;

class PricingCalculator
{
    /**
     * Ürün fiyatı = HAS × gram-sabiti × (1 + %marj) + sabit tutar.
     *
     * @param float|null $rawPrice      Kaynağın HAS (gram) fiyatı
     * @param float|null $hasFactor     Ürünün gram-altın içeriği (products.has_factor)
     * @param float      $marginPercent Mağaza yüzde marjı (işaretli; BID için negatif olabilir)
     * @param float      $marginAmount  Mağaza sabit tutar marjı (₺, işaretli)
     */
    public function compute(
        ?float $rawPrice,
        ?float $hasFactor,
        float $marginPercent = 0.0,
        float $marginAmount = 0.0
    ): ?float {
        if ($rawPrice === null || $hasFactor === null) {
            return null;
        }

        $base = $rawPrice * $hasFactor;

        return $base * (1 + $marginPercent / 100) + $marginAmount;
    }
}

