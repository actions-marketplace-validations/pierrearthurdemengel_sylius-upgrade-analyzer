<?php

declare(strict_types=1);

namespace App\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

class AdminMenuBuilder
{
    public function addCustomItems(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        $analyticsMenu = $menu->addChild('analytics')
            ->setLabel('Analytics');

        $analyticsMenu->addChild('sales_report', ['route' => 'app_admin_sales_report'])
            ->setLabel('Sales Report');

        $analyticsMenu->addChild('customer_analytics', ['route' => 'app_admin_customer_analytics'])
            ->setLabel('Customer Analytics');
    }
}
