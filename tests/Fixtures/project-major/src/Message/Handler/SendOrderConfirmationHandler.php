<?php

declare(strict_types=1);

namespace App\Message\Handler;

use App\Message\SendOrderConfirmation;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'sylius_default.bus')]
class SendOrderConfirmationHandler
{
    public function __invoke(SendOrderConfirmation $message): void
    {
        // Handle order confirmation email
    }
}
