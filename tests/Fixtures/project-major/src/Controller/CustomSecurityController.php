<?php

declare(strict_types=1);

namespace App\Controller;

use Sylius\Bundle\UiBundle\Controller\SecurityController;

class CustomSecurityController extends SecurityController
{
    public function loginAction(): void
    {
        // Custom login logic
    }
}
