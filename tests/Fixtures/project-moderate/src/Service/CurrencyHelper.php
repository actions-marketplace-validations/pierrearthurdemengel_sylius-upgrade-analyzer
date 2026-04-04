<?php

declare(strict_types=1);

namespace App\Service;

use Sylius\Bundle\CurrencyBundle\Templating\Helper\CurrencyHelper as BaseCurrencyHelper;

class CurrencyHelper
{
    public function __construct(private BaseCurrencyHelper $helper)
    {
    }

    public function getSymbol(string $code): string
    {
        return $this->helper->convertCurrencyCodeToSymbol($code);
    }
}
