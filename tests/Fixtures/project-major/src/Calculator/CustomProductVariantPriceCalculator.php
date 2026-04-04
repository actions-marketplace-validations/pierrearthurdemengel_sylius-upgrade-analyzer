<?php

declare(strict_types=1);

namespace App\Calculator;

use Sylius\Component\Core\Calculator\ProductVariantPriceCalculator;

class CustomProductVariantPriceCalculator extends ProductVariantPriceCalculator
{
    public function calculate(): int
    {
        return 0;
    }
}
