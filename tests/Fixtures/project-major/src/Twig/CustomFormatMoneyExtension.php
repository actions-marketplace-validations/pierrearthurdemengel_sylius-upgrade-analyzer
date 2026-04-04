<?php

declare(strict_types=1);

namespace App\Twig;

use Sylius\Bundle\MoneyBundle\Twig\Extension\FormatMoneyExtension;

class CustomFormatMoneyExtension extends FormatMoneyExtension
{
    public function formatMoney(int $amount): string
    {
        return '';
    }
}
