<?php

declare(strict_types=1);

namespace App\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;

#[ApiResource(
    normalizationContext: ['groups' => ['shop:order:read', 'admin:order:read']],
    denormalizationContext: ['groups' => ['shop:order:write']],
    operations: [
        new Get(),
        new GetCollection(),
    ],
)]
class OrderResource
{
    public int $id;
    public string $number;
    public string $state;
    public int $total;
}
