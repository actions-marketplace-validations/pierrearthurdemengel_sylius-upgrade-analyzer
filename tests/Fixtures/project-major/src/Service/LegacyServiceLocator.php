<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;

class LegacyServiceLocator
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    public function getProductManager(): object
    {
        return $this->container->get('sylius.manager.product');
    }

    public function getOrderFactory(): object
    {
        return $this->container->get('sylius.factory.order');
    }
}
