<?php

declare(strict_types=1);

namespace App\OrderProcessor;

use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

class CustomShippingProcessor implements OrderProcessorInterface
{
    public function process(OrderInterface $order): void
    {
        // Custom shipping calculation logic
        foreach ($order->getShipments() as $shipment) {
            // Recalculate shipping based on custom rules
        }
    }
}
