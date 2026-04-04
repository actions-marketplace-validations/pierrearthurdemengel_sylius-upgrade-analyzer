<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class LegacyShopController extends AbstractController
{
    public function homepageAction(): void
    {
        $channelContext = $this->get('sylius.context.channel');
        $localeContext = $this->get('sylius.context.locale');
    }

    public function categoryAction(): void
    {
        $taxonRepo = $this->container->get('sylius.repository.taxon');
    }
}
