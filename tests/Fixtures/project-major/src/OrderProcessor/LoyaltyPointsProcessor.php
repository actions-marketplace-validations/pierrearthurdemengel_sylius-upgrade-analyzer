<?php

declare(strict_types=1);

namespace App\OrderProcessor;

use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

class LoyaltyPointsProcessor implements OrderProcessorInterface
{
    public function process(OrderInterface $order): void
    {
        // Apply loyalty points discount based on customer tier
        $customer = $order->getCustomer();
        if ($customer === null) {
            return;
        }

        $loyaltyPoints = $customer->getLoyaltyPoints();
        if ($loyaltyPoints > 1000) {
            // Apply 5% discount
            $discount = (int) round($order->getTotal() * 0.05);
            // Custom discount logic here
        }
    }
}
