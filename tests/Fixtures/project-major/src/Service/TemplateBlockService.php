<?php

declare(strict_types=1);

namespace App\Service;

use Sylius\Bundle\UiBundle\Registry\TemplateBlock;
use Sylius\Bundle\UiBundle\Registry\TemplateBlockRegistry;
use Sylius\Bundle\UiBundle\Renderer\HtmlDebugTemplateBlockRenderer;
use Sylius\Bundle\UiBundle\ContextProvider\DefaultContextProvider;
use Sylius\Bundle\UiBundle\Twig\TemplateEventExtension;
use Sylius\Bundle\UiBundle\Twig\LegacySonataBlockExtension;
use Sylius\Bundle\UiBundle\Twig\SortByExtension;
use Sylius\Bundle\UiBundle\Twig\TestFormAttributeExtension;

class TemplateBlockService
{
    public function getBlocks(): array
    {
        return [];
    }
}
