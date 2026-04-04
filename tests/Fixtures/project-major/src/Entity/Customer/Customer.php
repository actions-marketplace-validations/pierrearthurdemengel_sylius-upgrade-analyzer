<?php

declare(strict_types=1);

namespace App\Entity\Customer;

use Sylius\Component\Core\Model\Customer as BaseCustomer;

class Customer extends BaseCustomer
{
    private ?string $salt = null;
    private ?string $loyaltyTier = null;
    private int $loyaltyPoints = 0;

    public function getSalt(): ?string
    {
        return $this->salt;
    }

    public function setSalt(?string $salt): void
    {
        $this->salt = $salt;
    }

    public function getLoyaltyTier(): ?string
    {
        return $this->loyaltyTier;
    }

    public function setLoyaltyTier(?string $loyaltyTier): void
    {
        $this->loyaltyTier = $loyaltyTier;
    }

    public function getLoyaltyPoints(): int
    {
        return $this->loyaltyPoints;
    }

    public function setLoyaltyPoints(int $loyaltyPoints): void
    {
        $this->loyaltyPoints = $loyaltyPoints;
    }
}
