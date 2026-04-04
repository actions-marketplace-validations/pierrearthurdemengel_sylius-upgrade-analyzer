<?php

declare(strict_types=1);

namespace App\Listener;

use Sylius\Bundle\CoreBundle\EventListener\OrderCompleteListener;

class CustomOrderCompleteListener extends OrderCompleteListener
{
    public function onOrderComplete(): void
    {
        // Custom order complete logic
    }
}
