<?php

declare(strict_types=1);

namespace App\Message\Handler;

use App\Message\NotifyWarehouse;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'sylius_event.bus')]
class NotifyWarehouseHandler
{
    public function __invoke(NotifyWarehouse $message): void
    {
        // Notify warehouse of shipment
    }
}
