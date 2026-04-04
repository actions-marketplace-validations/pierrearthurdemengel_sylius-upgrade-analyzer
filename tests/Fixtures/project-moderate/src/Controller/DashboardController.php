<?php

declare(strict_types=1);

namespace App\Controller;

class DashboardController
{
    public function redirectToDashboard(): string
    {
        return $this->redirectToRoute('sylius_admin_dashboard_statistics');
    }
}
