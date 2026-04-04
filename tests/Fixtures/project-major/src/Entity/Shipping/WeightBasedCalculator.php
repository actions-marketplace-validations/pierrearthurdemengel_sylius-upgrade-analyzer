<?php

declare(strict_types=1);

namespace App\Entity\Shipping;

use Sylius\Component\Shipping\Calculator\CalculatorInterface;
use Sylius\Component\Shipping\Model\ShipmentInterface;

class WeightBasedCalculator implements CalculatorInterface
{
    public const TYPE = 'weight_based';

    public function calculate(ShipmentInterface $subject, array $configuration): int
    {
        $weight = 0;
        foreach ($subject->getUnits() as $unit) {
            $weight += $unit->getShippable()->getWeight() ?? 0;
        }

        $pricePerKg = $configuration['price_per_kg'] ?? 500;
        $baseFee = $configuration['base_fee'] ?? 1000;

        return $baseFee + (int) ceil($weight * $pricePerKg);
    }

    public function getType(): string
    {
        return self::TYPE;
    }
}
