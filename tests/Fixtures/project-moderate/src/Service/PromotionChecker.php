<?php

declare(strict_types=1);

namespace App\Service;

use Sylius\Calendar\Provider\DateTimeProviderInterface;

class PromotionChecker
{
    public function __construct(private DateTimeProviderInterface $dateTimeProvider)
    {
    }

    public function isActive(): bool
    {
        return $this->dateTimeProvider->now() < new \DateTimeImmutable('+1 day');
    }
}
