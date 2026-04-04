<?php

declare(strict_types=1);

namespace App\Twig;

use Sylius\Bundle\CurrencyBundle\Twig\Extension\CurrencyExtension;

class CustomCurrencyExtension extends CurrencyExtension
{
    public function getCurrencyCode(): string
    {
        return 'EUR';
    }
}
