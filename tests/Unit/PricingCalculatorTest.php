<?php

namespace Tests\Unit;

use App\Pricing\PricingCalculator;
use PHPUnit\Framework\TestCase;

class PricingCalculatorTest extends TestCase
{
    public function test_it_computes_price_with_has_factor_only(): void
    {
        $calc = new PricingCalculator();
        // 100 × 1.6 × (1+0) + 0 = 160
        $this->assertSame(160.0, $calc->compute(100.0, 1.6));
    }

    public function test_it_applies_percent_and_amount_margin(): void
    {
        $calc = new PricingCalculator();
        // 100 × 2.0 × (1 + 2%) + 10 = 200 × 1.02 + 10 = 214
        $this->assertSame(214.0, $calc->compute(100.0, 2.0, 2.0, 10.0));
        // Negatif yüzde (alış): 100 × 2.0 × (1 - 1.5%) = 197
        $this->assertSame(197.0, $calc->compute(100.0, 2.0, -1.5, 0.0));
    }

    public function test_it_returns_null_when_missing_inputs(): void
    {
        $calc = new PricingCalculator();
        $this->assertNull($calc->compute(null, 1.6));
        $this->assertNull($calc->compute(100.0, null));
    }
}

