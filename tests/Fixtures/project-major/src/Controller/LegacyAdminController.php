<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class LegacyAdminController extends AbstractController
{
    public function exportAction(): void
    {
        $orderRepo = $this->get('sylius.repository.order');
        $container = $this->getContainer();
        $customerRepo = $container->get('sylius.repository.customer');
    }
}
