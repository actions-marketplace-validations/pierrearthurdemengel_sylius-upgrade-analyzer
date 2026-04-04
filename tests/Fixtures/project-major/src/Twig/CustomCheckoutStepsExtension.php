<?php

declare(strict_types=1);

namespace App\Twig;

use Sylius\Bundle\ShopBundle\Twig\Extension\CheckoutStepsExtension;

class CustomCheckoutStepsExtension extends CheckoutStepsExtension
{
    public function getSteps(): array
    {
        return [];
    }
}
