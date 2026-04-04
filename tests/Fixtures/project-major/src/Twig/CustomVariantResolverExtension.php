<?php

declare(strict_types=1);

namespace App\Twig;

use Sylius\Bundle\ProductBundle\Twig\Extension\VariantResolverExtension;

class CustomVariantResolverExtension extends VariantResolverExtension
{
    public function resolve(): mixed
    {
        return null;
    }
}
