<?php

declare(strict_types=1);

namespace App\Twig;

use Sylius\Bundle\ShopBundle\Twig\Extension\PriceExtension;

class CustomPriceExtension extends PriceExtension
{
    public function getPrice(): string
    {
        return parent::getPrice();
    }
}
