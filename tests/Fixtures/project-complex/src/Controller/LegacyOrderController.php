<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class LegacyOrderController extends AbstractController
{
    public function dashboardAction(): void
    {
        $productRepo = $this->get('sylius.repository.product');
        $orderRepo = $this->container->get('sylius.repository.order');
    }

    public function statsAction(): void
    {
        $channelContext = $this->get('sylius.context.channel');
    }
}
