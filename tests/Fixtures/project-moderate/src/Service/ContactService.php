<?php

declare(strict_types=1);

namespace App\Service;

use Sylius\Bundle\ShopBundle\EmailManager\ContactEmailManager;

class ContactService
{
    public function __construct(
        private readonly ContactEmailManager $contactEmailManager,
    ) {
    }

    public function sendContactForm(array $data): void
    {
        $this->contactEmailManager->sendContactRequest($data);
    }
}
