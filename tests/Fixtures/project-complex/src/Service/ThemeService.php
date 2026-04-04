<?php

declare(strict_types=1);

namespace App\Service;

use Sylius\Bundle\CoreBundle\Theme\ChannelBasedThemeContext;

class ThemeService
{
    public function __construct(
        private readonly ChannelBasedThemeContext $themeContext,
    ) {
    }

    public function getCurrentTheme(): ?string
    {
        return $this->themeContext->getTheme()?->getName();
    }
}
