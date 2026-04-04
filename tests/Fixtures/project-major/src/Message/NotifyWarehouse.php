<?php

declare(strict_types=1);

namespace App\Message;

class NotifyWarehouse
{
    public function __construct(
        private readonly int $shipmentId,
        private readonly string $warehouseCode,
    ) {
    }

    public function getShipmentId(): int
    {
        return $this->shipmentId;
    }

    public function getWarehouseCode(): string
    {
        return $this->warehouseCode;
    }
}
