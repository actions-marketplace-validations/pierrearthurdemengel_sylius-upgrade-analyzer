<?php

declare(strict_types=1);

namespace App\Service;

use Sylius\Bundle\UiBundle\Storage\FilterStorageInterface;
use Sylius\Bundle\UiBundle\Storage\FilterStorage;

class FilterService
{
    public function __construct(
        private readonly FilterStorageInterface $filterStorage,
    ) {
    }

    public function getFilters(): array
    {
        return $this->filterStorage->all();
    }
}
