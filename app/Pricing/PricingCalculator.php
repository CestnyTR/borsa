<?php

namespace App\Pricing;

class PricingCalculator
{
    public function compute(?float $rawPrice, ?float $multiplier): ?float
    {
        if ($rawPrice === null || $multiplier === null) {
            return null;
        }

        return $rawPrice * $multiplier;
    }
}

