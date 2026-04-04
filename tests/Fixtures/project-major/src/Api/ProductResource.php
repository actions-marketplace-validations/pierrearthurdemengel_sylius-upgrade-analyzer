<?php

declare(strict_types=1);

namespace App\Api;

use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;

/**
 * @ApiResource(
 *     collectionOperations={"get", "post"},
 *     itemOperations={"get", "put", "delete"},
 *     normalizationContext={"groups"={"admin:product:read"}},
 *     denormalizationContext={"groups"={"admin:product:write"}}
 * )
 * @ApiFilter(SearchFilter::class, properties={"name": "partial", "code": "exact"})
 */
class ProductResource
{
    public int $id;
    public string $name;
    public string $code;
    public int $price;
}
