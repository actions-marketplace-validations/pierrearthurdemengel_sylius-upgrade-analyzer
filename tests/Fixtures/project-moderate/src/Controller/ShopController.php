<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ShopController extends AbstractController
{
    public function indexAction(): void
    {
        $channelContext = $this->get('sylius.context.channel');
        $channel = $channelContext->getChannel();
    }
}
