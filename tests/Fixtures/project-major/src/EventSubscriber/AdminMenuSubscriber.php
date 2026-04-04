<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AdminMenuSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'sylius.menu.admin.main' => 'addAdminMenuItems',
            'sylius.menu.admin.sidebar' => 'addSidebarItems',
        ];
    }

    public function addAdminMenuItems(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        $customSection = $menu->addChild('custom')
            ->setLabel('Custom Section');

        $customSection->addChild('reports', ['route' => 'app_admin_report_index'])
            ->setLabel('Reports');

        $customSection->addChild('exports', ['route' => 'app_admin_export_index'])
            ->setLabel('Data Exports');

        $customSection->addChild('loyalty', ['route' => 'app_admin_loyalty_index'])
            ->setLabel('Loyalty Program');
    }

    public function addSidebarItems(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        $menu->addChild('warehouse', ['route' => 'app_admin_warehouse_index'])
            ->setLabel('Warehouse')
            ->setLabelAttribute('icon', 'warehouse');
    }
}
