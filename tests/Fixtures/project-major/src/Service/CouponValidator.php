<?php

declare(strict_types=1);

namespace App\Service;

use Sylius\Calendar\Provider\DateTimeProviderInterface;

class CouponValidator
{
    public function __construct(private DateTimeProviderInterface $dateTimeProvider)
    {
    }

    public function isValid(\DateTimeImmutable $validUntil): bool
    {
        return $this->dateTimeProvider->now() <= $validUntil;
    }
}
