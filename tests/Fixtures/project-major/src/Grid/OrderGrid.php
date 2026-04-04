<?php

declare(strict_types=1);

namespace App\Grid;

use Sylius\Bundle\GridBundle\Builder\GridBuilder;
use Sylius\Bundle\GridBundle\Grid\AbstractGrid;
use Sylius\Bundle\GridBundle\Grid\ResourceAwareGridInterface;

class OrderGrid extends AbstractGrid implements ResourceAwareGridInterface
{
    public function getResourceClass(): string
    {
        return \App\Entity\Order\Order::class;
    }

    public function buildGrid(GridBuilder $gridBuilder): void
    {
        $gridBuilder
            ->addField(
                'number',
                'string',
                ['label' => 'sylius.ui.number']
            )
            ->addField(
                'date',
                'datetime',
                ['label' => 'sylius.ui.date', 'options' => ['format' => 'd-m-Y H:i']]
            )
        ;
    }
}
