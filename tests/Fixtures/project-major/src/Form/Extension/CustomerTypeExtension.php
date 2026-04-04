<?php

declare(strict_types=1);

namespace App\Form\Extension;

use Sylius\Bundle\CoreBundle\Form\Type\Customer\CustomerProfileType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class CustomerTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('loyaltyTier', TextType::class, [
                'label' => 'app.form.customer.loyalty_tier',
                'required' => false,
            ])
            ->add('loyaltyPoints', IntegerType::class, [
                'label' => 'app.form.customer.loyalty_points',
                'required' => false,
            ])
        ;
    }

    public static function getExtendedTypes(): iterable
    {
        return [CustomerProfileType::class];
    }

    /**
     * @deprecated Use getExtendedTypes() instead
     */
    public function getExtendedType(): string
    {
        return CustomerProfileType::class;
    }
}
