<?php

declare(strict_types=1);

namespace App\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;

#[ApiResource(
    normalizationContext: ['groups' => ['admin:product:index', 'admin:product:read']],
    denormalizationContext: ['groups' => ['admin:product:write']],
    operations: [
        new GetCollection(),
    ],
)]
class ProductResource
{
    public int $id;
    public string $name;
    public string $code;
}
