<?php

namespace Tests\Unit;

use App\Pricing\PricingCalculator;
use PHPUnit\Framework\TestCase;

class PricingCalculatorTest extends TestCase
{
    public function test_it_computes_price_with_multiplier(): void
    {
        $calc = new PricingCalculator();
        $this->assertSame(91.1, $calc->compute(100.0, 0.911));
    }

    public function test_it_returns_null_when_missing_inputs(): void
    {
        $calc = new PricingCalculator();
        $this->assertNull($calc->compute(null, 0.9));
        $this->assertNull($calc->compute(100.0, null));
    }
}

