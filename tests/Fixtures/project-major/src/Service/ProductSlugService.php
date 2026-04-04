<?php

declare(strict_types=1);

namespace App\Service;

use Sylius\Bundle\ProductBundle\Controller\ProductSlugController;
use Sylius\Bundle\ProductBundle\Controller\ProductAttributeController;
use Sylius\Bundle\ProductBundle\Form\Type\ProductOptionChoiceType;
use Sylius\Bundle\PayumBundle\Controller\PayumController;
use Sylius\Bundle\UserBundle\Security\UserLogin;
use Sylius\Bundle\UserBundle\Security\UserPasswordHasher;
use Sylius\Component\User\Security\Generator\UniquePinGenerator;

class ProductSlugService
{
    public function generate(): string
    {
        return '';
    }
}
