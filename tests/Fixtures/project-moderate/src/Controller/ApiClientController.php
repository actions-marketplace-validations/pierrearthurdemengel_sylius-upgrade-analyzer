<?php

declare(strict_types=1);

namespace App\Controller;

class ApiClientController
{
    public function resetPassword(): void
    {
        $url = '/api/v2/shop/reset-password-requests';
        // Appel API pour reinitialiser le mot de passe
    }
}
