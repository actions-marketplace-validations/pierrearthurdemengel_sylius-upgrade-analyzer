<?php

declare(strict_types=1);

namespace App\Service;

use Sylius\Bundle\AdminBundle\EmailManager\ShipmentEmailManager;
use Sylius\Bundle\AdminBundle\EmailManager\ShipmentEmailManagerInterface;

class ShipmentService
{
    public function __construct(
        private readonly ShipmentEmailManagerInterface $emailManager,
    ) {
    }

    public function notifyShipment(int $shipmentId): void
    {
        $this->emailManager->sendConfirmationEmail($shipmentId);
    }
}
