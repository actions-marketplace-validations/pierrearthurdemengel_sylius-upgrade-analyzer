<?php

declare(strict_types=1);

namespace App\Listener;

use Sylius\Bundle\CoreBundle\EventListener\ShipmentShipListener;

class CustomShipmentListener extends ShipmentShipListener
{
    public function onShipmentShip(): void
    {
        // Custom logic
    }
}
