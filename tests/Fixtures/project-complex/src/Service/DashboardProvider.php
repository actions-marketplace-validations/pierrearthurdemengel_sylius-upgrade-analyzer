<?php

declare(strict_types=1);

namespace App\Service;

use Sylius\Component\Core\Dashboard\DashboardStatistics;
use Sylius\Bundle\AdminBundle\Controller\Dashboard\StatisticsController;
use Sylius\Bundle\AdminBundle\Provider\StatisticsDataProvider;

class DashboardProvider
{
    public function __construct(
        private StatisticsDataProvider $provider,
    ) {
    }

    public function getStatistics(): array
    {
        return [];
    }
}
