<?php

declare(strict_types=1);

namespace App\Service;

class ExpirationChecker
{
    public function isExpired(\DateTimeImmutable $date): bool
    {
        /** @var \Sylius\Calendar\Provider\DateTimeProviderInterface $provider */
        $provider = $this->getProvider();

        return $provider->now() > $date;
    }

    private function getProvider(): object
    {
        return new \stdClass();
    }
}
