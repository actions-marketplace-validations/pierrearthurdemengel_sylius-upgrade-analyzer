<?php

declare(strict_types=1);

namespace App\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    normalizationContext: ['groups' => ['admin:customer:read']],
)]
class CustomerResource
{
    public int $id;

    #[Groups(['admin:customer:read', 'shop:customer:read'])]
    public string $email;

    #[Groups(['admin:customer:read'])]
    public string $firstName;
}
